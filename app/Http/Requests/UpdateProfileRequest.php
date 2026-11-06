<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user())],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Escribe tu nombre completo.',
            'name.string' => 'El nombre debe ser texto válido.',
            'name.max' => 'El nombre no puede superar 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.string' => 'El correo electrónico debe ser texto válido.',
            'email.email' => 'Escribe una dirección de correo válida.',
            'email.max' => 'El correo no puede superar 255 caracteres.',
            'email.unique' => 'Ese correo ya está asociado a otra cuenta.',
            'photo.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'photo.mimes' => 'La foto debe estar en formato JPG, PNG o WEBP.',
            'photo.extensions' => 'La extensión de la foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto no puede pesar más de 5 MB.',
            'photo.uploaded' => 'No se pudo cargar la foto. Inténtalo nuevamente.',
            'remove_photo.boolean' => 'La opción para eliminar la foto no es válida.',
        ];
    }
}
