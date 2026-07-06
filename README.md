# NexERP — Sistem ERP Modular

**Laravel 11 · PostgreSQL · Redis · Sanctum JWT · L5-Swagger**

ERP Modular multi-tenant dengan 5 modul: **User, HRD, Marketing, Finance, Project**. Setiap modul dapat diaktifkan / dinonaktifkan per-klien.

---

## Instalasi

```bash
# 1. Clone repo
git clone https://github.com/danielandii/erpku.git
cd erpku

# 2. Install dependencies
composer install

# 3. Copy env
cp .env.example .env
php artisan key:generate

# 4. Buat database PostgreSQL
createdb erpku

# 5. Jalankan migrasi + seeder
php artisan migrate --seed

# 6. Generate Swagger docs
php artisan l5-swagger:generate

# 7. Jalankan server
php artisan serve
```

---

## Akses

| Resource          | URL                                       |
|-------------------|-------------------------------------------|
| API Base URL      | `http://localhost:8000/api/v1`            |
| Swagger UI        | `http://localhost:8000/api/documentation` |
| Swagger JSON      | `http://localhost:8000/docs/api-docs.json`|

### Default Login (Seeder)

| User              | Email                     | Password   | Role         |
|-------------------|---------------------------|------------|--------------|
| Rizky Kurniawan   | rizky@majujaya.co.id      | `password` | Super Admin  |
| Sari Andini       | sari@majujaya.co.id       | `password` | HR Manager   |
| Maya Rahayu       | maya@majujaya.co.id       | `password` | Finance Mgr  |
| Dewi Wulandari    | dewi@majujaya.co.id       | `password` | Sales Mgr    |
| Ahmad Hidayat     | ahmad@majujaya.co.id      | `password` | Project Mgr  |
| Budi Santoso      | budi@majujaya.co.id       | `password` | Staff        |
| Fauzan Hidayat    | fauzan@majujaya.co.id     | `password` | Staff        |

---

## Autentikasi

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "rizky@majujaya.co.id",
  "password": "password"
}
```

Response:
```json
{
  "success": true,
  "data": {
    "access_token": "...",
    "refresh_token": "...",
    "token_type": "Bearer",
    "expires_in": 900
  }
}
```

Gunakan `Authorization: Bearer {access_token}` di setiap request.

---

## Struktur File Utama

```
erpku/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── Auth/         AuthController
│   │   │   ├── User/         UserController, RoleController, PermissionController, AuditLogController
│   │   │   ├── Hrd/          EmployeeController, AttendanceController, LeaveRequestController,
│   │   │   │                 PayrollController, SalaryStructureController, LeaveTypeController,
│   │   │   │                 LeaveAllocationController, DepartmentController, PositionController,
│   │   │   │                 WorkScheduleController, HolidayController, AttendancePermissionController
│   │   │   ├── Marketing/    LeadController, LeadStageController, QuotationController,
│   │   │   │                 QuotationTemplateController, ClientController, ClientContactController
│   │   │   ├── Finance/      InvoiceController, PaymentController, BillController,
│   │   │   │                 JournalController, CoaController, BankAccountController, ReportController
│   │   │   ├── Project/      ProjectController, TaskController, TaskStageController,
│   │   │   │                 ProjectMemberController, TaskAssigneeController, TaskDependencyController,
│   │   │   │                 TaskCommentController, TaskChecklistController, TaskTimeLogController,
│   │   │   │                 TaskAttachmentController
│   │   │   ├── DashboardController
│   │   │   ├── NotificationController
│   │   │   ├── SettingController
│   │   │   └── BaseController
│   │   ├── Middleware/
│   │   │   ├── ModuleGuard.php       Cek modul aktif per tenant
│   │   │   └── CheckPermission.php   RBAC permission check
│   │   └── Resources/
│   │       ├── User/         UserResource, RoleResource, LoginHistoryResource
│   │       ├── Hrd/          EmployeeResource, AttendanceResource, LeaveRequestResource,
│   │       │                 PayrollItemResource, PayrollPeriodResource, LeaveAllocationResource,
│   │       │                 AttendancePermissionResource
│   │       ├── Marketing/    LeadResource, LeadActivityResource, QuotationResource, ClientResource
│   │       ├── Finance/      InvoiceResource, PaymentResource, JournalEntryResource,
│   │       │                 CoaResource, BillResource, BankAccountResource
│   │       └── Project/      ProjectResource, TaskResource, TaskCommentResource, TaskTimeLogResource
│   ├── Models/               Semua Eloquent Models (30+ model)
│   ├── Services/
│   │   ├── Hrd/PayrollService.php    Kalkulasi gaji, PPh21, BPJS, jurnal payroll
│   │   └── Finance/InvoiceService.php  Invoice creation + auto double-entry journal
│   └── Traits/
│       ├── HasUuid.php               Auto UUID primary key
│       ├── HasTenant.php             Multi-tenant global scope
│       ├── HasDocumentNumber.php     Atomic auto-numbering (INV-2024-0001)
│       └── HasAuditLog.php           Auto audit trail
├── database/
│   ├── migrations/           17 migration files (urutan sudah benar)
│   └── seeders/              10 seeder (permissions, roles, users, COA, dll)
├── routes/
│   └── api.php               90+ endpoint terorganisir per modul
├── config/
│   └── l5-swagger.php        Konfigurasi Swagger UI
└── bootstrap/
    └── app.php               Middleware registration + JSON error handler
```

---

## Format Response API

### Success
```json
{
  "success": true,
  "message": "OK",
  "data": { ... }
}
```

### Paginated
```json
{
  "success": true,
  "message": "OK",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 87
  }
}
```

### Error
```json
{
  "success": false,
  "message": "Pesan error yang deskriptif",
  "code": "VALIDATION_ERROR",
  "errors": { "field": ["pesan"] }
}
```

---

## Permission Format

Format permission: `{module}.{resource}.{action}`

Contoh:
- `hrd.attendance.create` — Clock-in karyawan
- `finance.invoices.approve` — Kirim invoice
- `project.tasks.assign` — Assign task ke anggota
- `user.roles.update` — Edit role & permission

---

## Middleware

| Middleware       | Penggunaan                                       |
|------------------|--------------------------------------------------|
| `auth:sanctum`   | Wajib login (semua protected route)              |
| `module:hrd`     | Modul HRD harus aktif untuk tenant               |
| `permission:hrd.attendance.create` | User harus punya permission ini |

---

## Menjalankan Queue (untuk export, notifikasi, email)

```bash
php artisan queue:work redis --queue=default,notifications,exports
```

---

## Generate / Refresh Swagger

```bash
php artisan l5-swagger:generate
```

Buka: `http://localhost:8000/api/documentation`

---

## Tips Development

```bash
# Refresh database + seed ulang
php artisan migrate:fresh --seed

# Clear semua cache
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# Lihat semua route API
php artisan route:list --path=api

# Monitor queue
php artisan queue:monitor redis:default

# Telescope (dev monitoring)
php artisan telescope:install
```
