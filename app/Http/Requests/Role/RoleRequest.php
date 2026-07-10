<?php

namespace App\Http\Requests\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

public function rules()
{
    $roleId = $this->route('id');

    return [
        'role_name' => [
            'required',
            'max:50',
            Rule::unique('roles', 'role_name')->ignore($roleId),
        ],
        'description' => 'required',
    ];
}

}
