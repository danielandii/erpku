<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $employeeId = $this->route('id');

        return [
            'full_name'         => ['sometimes', 'string', 'max:255'],
            'nik_ktp'           => ['sometimes', 'nullable', 'string', 'size:16'],
            'npwp'              => ['sometimes', 'nullable', 'string', 'max:30'],
            'birth_place'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'birth_date'        => ['sometimes', 'nullable', 'date'],
            'gender'            => ['sometimes', 'nullable', 'in:male,female'],
            'marital_status'    => ['sometimes', 'nullable', 'in:single,married,divorced,widowed'],
            'num_dependants'    => ['sometimes', 'nullable', 'in:TK0,TK1,TK2,TK3,K0,K1,K2,K3'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'email_personal'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'department_id'     => ['sometimes', 'nullable', 'uuid', 'exists:departments,id'],
            'position_id'       => ['sometimes', 'nullable', 'uuid', 'exists:positions,id'],
            'work_schedule_id'  => ['sometimes', 'nullable', 'uuid', 'exists:work_schedules,id'],
            'manager_id'        => ['sometimes', 'nullable', 'uuid', 'exists:employees,id'],
            'employment_type'   => ['sometimes', 'in:permanent,contract,freelance,intern'],
            'contract_end_date' => ['sometimes', 'nullable', 'date'],
            'resign_date'       => ['sometimes', 'nullable', 'date'],
            'bank_name'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'bank_account'      => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bpjs_kes_number'   => ['sometimes', 'nullable', 'string', 'max:30'],
            'bpjs_tk_number'    => ['sometimes', 'nullable', 'string', 'max:30'],
            'photo_url'         => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }
}
