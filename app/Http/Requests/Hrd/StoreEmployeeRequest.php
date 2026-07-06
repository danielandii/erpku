<?php

namespace App\Http\Requests\Hrd;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'employee_number'   => ['required', 'string', 'max:50', 'unique:employees,employee_number'],
            'full_name'         => ['required', 'string', 'max:255'],
            'nik_ktp'           => ['nullable', 'string', 'size:16'],
            'npwp'              => ['nullable', 'string', 'max:30'],
            'birth_place'       => ['nullable', 'string', 'max:100'],
            'birth_date'        => ['nullable', 'date', 'before:today'],
            'gender'            => ['nullable', 'in:male,female'],
            'marital_status'    => ['nullable', 'in:single,married,divorced,widowed'],
            'num_dependants'    => ['nullable', 'in:TK0,TK1,TK2,TK3,K0,K1,K2,K3'],
            'address'           => ['nullable', 'string', 'max:500'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'email_personal'    => ['nullable', 'email', 'max:255'],
            'department_id'     => ['nullable', 'uuid', 'exists:departments,id'],
            'position_id'       => ['nullable', 'uuid', 'exists:positions,id'],
            'work_schedule_id'  => ['nullable', 'uuid', 'exists:work_schedules,id'],
            'manager_id'        => ['nullable', 'uuid', 'exists:employees,id'],
            'join_date'         => ['required', 'date'],
            'employment_type'   => ['required', 'in:permanent,contract,freelance,intern'],
            'contract_end_date' => ['nullable', 'date', 'after:join_date', 'required_if:employment_type,contract'],
            'bank_name'         => ['nullable', 'string', 'max:100'],
            'bank_account'      => ['nullable', 'string', 'max:50'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bpjs_kes_number'   => ['nullable', 'string', 'max:30'],
            'bpjs_tk_number'    => ['nullable', 'string', 'max:30'],
            'photo_url'         => ['nullable', 'url', 'max:500'],
            'create_user_account' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_number.unique'         => 'Nomor karyawan sudah digunakan.',
            'contract_end_date.required_if'  => 'Tanggal akhir kontrak wajib diisi untuk karyawan kontrak.',
            'birth_date.before'              => 'Tanggal lahir harus sebelum hari ini.',
        ];
    }
}
