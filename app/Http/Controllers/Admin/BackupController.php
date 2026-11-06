<?php

namespace App\Http\Controllers\Admin;

use App\AccessControl;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(Config $config): View
    {
        $this->authorizeSuperAdministrator();
        $backups = $this->backups($config);

        return view('admin.backups.index', [
            'backups' => $backups,
            'destinations' => BackupDestinationFactory::createFromArray($config)
                ->map(fn ($destination): array => [
                    'name' => $destination->diskName(),
                    'reachable' => $destination->isReachable(),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdministrator();
        $exitCode = Artisan::call('backup:run');

        if ($exitCode !== 0) {
            activity('backups')
                ->causedBy($request->user())
                ->event('failed')
                ->log('Falló la creación de una copia de seguridad');

            return to_route('admin.backups.index')
                ->with('error', 'No se pudo completar el respaldo. Revisa la configuración y los registros del sistema.');
        }

        activity('backups')
            ->causedBy($request->user())
            ->event('created')
            ->log('Generó una copia de seguridad');

        return to_route('admin.backups.index')->with('success', 'La copia de seguridad se generó correctamente.');
    }

    public function download(Config $config, string $backupId): StreamedResponse
    {
        $this->authorizeSuperAdministrator();
        $backup = $this->backups($config)->firstWhere('id', $backupId);
        abort_unless($backup instanceof Backup, 404);

        $stream = $backup->stream();

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, basename($backup->path()), [
            'Content-Type' => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return list<array{id: string, disk: string, file: string, date: Carbon, size: float}>
     */
    private function backups(Config $config): array
    {
        return BackupDestinationFactory::createFromArray($config)
            ->flatMap(fn ($destination) => $destination->backups()->map(fn (Backup $backup): array => [
                'id' => hash('sha256', $destination->diskName()."\0".$backup->path()),
                'disk' => $destination->diskName(),
                'file' => basename($backup->path()),
                'date' => $backup->date(),
                'size' => $backup->sizeInBytes(),
            ]))
            ->sortByDesc(fn (array $backup): int => $backup['date']->timestamp)
            ->values()
            ->all();
    }

    private function authorizeSuperAdministrator(): void
    {
        abort_unless(auth()->user()->hasRole(AccessControl::SUPER_ADMIN_ROLE), 403);
    }
}
