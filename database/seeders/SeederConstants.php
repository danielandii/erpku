<?php

namespace Database\Seeders;

/**
 * Konstanta UUID terpusat untuk semua seeder.
 * Semua ID menggunakan format UUID v5 yang valid untuk PostgreSQL.
 * JANGAN ubah nilai ini setelah production seed dilakukan.
 */
class SeederConstants
{
    // ── Tenant ────────────────────────────────────────────────────────────────
    public const TENANT_ID                      = 'cd71dc73-df26-5f46-ab75-8254098badcc';

    // ── Roles ─────────────────────────────────────────────────────────────────
    public const ROLE_SUPER_ADMIN               = '929efc0f-2f86-5802-8266-41b7531246e1';
    public const ROLE_ADMIN                     = 'b3b3ddb9-21f0-55c5-afde-2a43f5e7cd30';
    public const ROLE_HR_MANAGER                = 'c2a1cc2f-b1d1-56b3-8d66-5c69f34ba70e';
    public const ROLE_FINANCE_MANAGER           = '90c32023-ed26-5ffc-95fd-a6b5465e1015';
    public const ROLE_SALES_MANAGER             = '8f2bc711-3d33-56f4-af60-b88cb7aac2df';
    public const ROLE_PROJECT_MANAGER           = '84f3d674-edae-5620-ae4d-436f1d44850d';
    public const ROLE_STAFF                     = '3e53d411-a314-5f10-8d05-6fa53bb8f953';
    public const ROLE_VIEWER                    = 'c82641dc-7e9c-5e17-b367-a38264f2b9c5';

    // ── Users ─────────────────────────────────────────────────────────────────
    public const USER_SUPER_ADMIN               = 'baef584d-53ea-53e2-b788-167ad75d59dd';
    public const USER_HR_MANAGER                = '14443ad8-dd12-5615-8597-5896f2937527';
    public const USER_FINANCE_MANAGER           = 'b11c629e-a3ee-5882-b891-14efce05eac5';
    public const USER_SALES_MANAGER             = 'edeec4f7-e9ba-5791-b577-fbd32eebf934';
    public const USER_PROJECT_MANAGER           = '4838a896-8461-51c0-b580-0510796f2b21';
    public const USER_STAFF_1                   = '80837626-c1a9-5c11-ac19-c8973fda9ffd';
    public const USER_STAFF_2                   = '416a1ef0-7f83-5db4-a867-7bcb94917d82';

    // ── Departments ───────────────────────────────────────────────────────────
    public const DEPT_IT                        = '322ccb45-7d3d-5e88-b477-6cb77bb231c6';
    public const DEPT_HRD                       = '94102684-d7a9-51b1-b030-d9fe156f78ca';
    public const DEPT_FINANCE                   = '26856baa-d9ab-5271-ae0e-2ecfe65d476e';
    public const DEPT_MARKETING                 = '44111ceb-5f54-5fbe-b6e2-3f97e3e34573';
    public const DEPT_OPERATIONS                = 'd15660dc-fec6-500a-9090-583c9bb1caa8';
    public const DEPT_PROJECT                   = 'c972d7b1-d23b-5d99-aee0-44240f56f48b';

    // ── Positions ─────────────────────────────────────────────────────────────
    public const POS_IT_DIRECTOR                = '1d45b928-b986-5968-8e3a-85d390b6f307';
    public const POS_IT_DEV                     = 'bf01d2c8-207b-52eb-8fb7-3c009b2505db';
    public const POS_IT_SYSADMIN                = 'e697c4eb-cc90-5d14-9c9a-6a2c26849d71';
    public const POS_HR_MANAGER                 = 'd8c07796-08b8-5eec-a1bb-aa16a051753a';
    public const POS_HR_STAFF                   = 'b45c1302-2cf9-51a8-bffa-f40ee95ea3f8';
    public const POS_HR_RECRUITMENT             = '7a023734-e27e-52c9-a5a8-620774b6d43d';
    public const POS_FIN_MANAGER                = 'd6b0d122-af75-5c0a-9321-5f7e34262ea6';
    public const POS_ACCOUNTANT                 = 'ad3a260d-e23a-5d12-89a9-9d7bda9bd7c0';
    public const POS_TAX                        = 'd4b63448-dba9-5beb-94de-aaca21f3c0eb';
    public const POS_SALES_MANAGER              = '6cd6cf48-b7a1-5420-8cd1-d1ae3ff60eb5';
    public const POS_SALES_EXEC                 = 'cd9258a9-0133-55d6-a319-b53fb672ff2f';
    public const POS_MARKETING                  = 'd7585afb-1651-5090-879d-2fc65e06dc7c';
    public const POS_PM                         = 'f68b4d6b-2c47-5aa9-8138-be0a30456209';
    public const POS_BUSINESS_ANALYST           = '6244a521-bbae-5e8f-9bdc-2f9a0e31e055';

    // ── Work Schedules ────────────────────────────────────────────────────────
    public const SCHED_REGULAR                  = '080153a7-6277-5e6b-9696-fe2420174ee1';
    public const SCHED_SHIFT_PAGI               = '21d3b0c9-03a6-56f8-9c76-c722f98c6ae7';
    public const SCHED_SHIFT_MALAM              = '0c823aee-8cce-5142-9f6e-82ce3c63eb96';

    // ── Employees ─────────────────────────────────────────────────────────────
    public const EMP_RIZKY                      = 'b1a09137-dffa-579c-9e19-0843398c758b';
    public const EMP_SARI                       = 'fac64758-cbba-5506-8fcf-896acb2a3ac8';
    public const EMP_MAYA                       = 'cdfd04b5-dbad-563f-a197-da43a6ed5797';
    public const EMP_DEWI                       = '2da04692-73cf-5f2b-bc9d-13bb7c9ee71b';
    public const EMP_AHMAD                      = '22936ab1-cc0f-5881-ada5-0976449baf69';
    public const EMP_BUDI                       = '77c34f2a-7602-58c4-94ea-563a25cc4cd8';
    public const EMP_FAUZAN                     = 'a18bc29b-a3ca-5687-9b12-43582c1f85ff';
}
