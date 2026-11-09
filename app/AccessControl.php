<?php

namespace App;

class AccessControl
{
    public const SUPER_ADMIN_ROLE = 'superadministrador';

    public const ADMIN_ROLE = 'administrador';

    public const OPERATOR_ROLE = 'operador';

    public const PERMISSION_LABELS = [
        'dashboard.view' => 'Consultar el panel general',
        'animals.view' => 'Consultar animales',
        'animals.create' => 'Registrar animales',
        'animals.update' => 'Editar animales y sus eventos',
        'animals.delete' => 'Archivar animales',
        'farms.view' => 'Consultar fincas',
        'farms.create' => 'Registrar fincas',
        'farms.update' => 'Editar fincas',
        'farms.delete' => 'Archivar fincas',
        'paddocks.view' => 'Consultar potreros',
        'paddocks.create' => 'Registrar potreros',
        'paddocks.update' => 'Editar potreros',
        'paddocks.delete' => 'Archivar potreros',
        'animal-movements.view' => 'Consultar compras, ventas y traslados',
        'animal-movements.create' => 'Registrar compras, ventas y traslados',
        'reproduction.view' => 'Consultar reproducción',
        'reproduction.create' => 'Registrar eventos reproductivos',
        'milk-production.view' => 'Consultar producción de leche',
        'milk-production.create' => 'Registrar producción de leche',
        'milk-production.update' => 'Editar registros de producción',
        'weights.view' => 'Consultar pesajes',
        'weights.create' => 'Registrar pesajes',
        'finance.view' => 'Consultar finanzas y costos',
        'finance.transactions.create' => 'Registrar ingresos y gastos',
        'finance.valuations.create' => 'Valorar animales',
        'reports.view' => 'Consultar reportes',
        'reports.export' => 'Exportar reportes',
        'users.view' => 'Consultar usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Editar usuarios',
        'users.delete' => 'Eliminar usuarios',
        'roles.view' => 'Consultar roles',
        'roles.create' => 'Crear roles',
        'roles.update' => 'Editar roles',
        'roles.delete' => 'Eliminar roles',
        'permissions.manage' => 'Administrar permisos del sistema',
        'animals.import' => 'Importar animales',
        'animals.export' => 'Exportar animales',
        'activity-log.view' => 'Consultar bitácora de auditoría',
        'backups.manage' => 'Administrar copias de seguridad',
        'catalogs.manage' => 'Administrar especies y razas',
    ];

    public const ADMIN_PERMISSIONS = [
        'dashboard.view',
        'animals.view', 'animals.create', 'animals.update', 'animals.delete',
        'farms.view', 'farms.create', 'farms.update', 'farms.delete',
        'paddocks.view', 'paddocks.create', 'paddocks.update', 'paddocks.delete',
        'animal-movements.view', 'animal-movements.create',
        'reproduction.view', 'reproduction.create',
        'milk-production.view', 'milk-production.create', 'milk-production.update',
        'weights.view', 'weights.create',
        'finance.view', 'finance.transactions.create', 'finance.valuations.create',
        'reports.view', 'reports.export',
        'users.view', 'users.create', 'users.update', 'users.delete',
        'animals.import', 'animals.export', 'activity-log.view',
        'catalogs.manage',
    ];

    public const OPERATOR_PERMISSIONS = [
        'dashboard.view',
        'animals.view', 'animals.create', 'animals.update',
        'farms.view',
        'paddocks.view',
        'animal-movements.view', 'animal-movements.create',
        'reproduction.view', 'reproduction.create',
        'milk-production.view', 'milk-production.create', 'milk-production.update',
        'weights.view', 'weights.create',
        'finance.view',
        'reports.view',
        'animals.export',
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function permissionGroups(): array
    {
        $groups = [];

        foreach (self::PERMISSION_LABELS as $name => $label) {
            $groupName = str_contains($name, '.') ? str($name)->before('.')->replace('-', ' ')->title()->toString() : 'Sistema';
            $groups[$groupName][$name] = $label;
        }

        ksort($groups);

        return $groups;
    }
}
