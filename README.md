PATCH 0.1 :

Model :

-   nama huruf T besar di awal untuk data table (T_user, T_karyawan, T_member)
-   nama huruf M besar di awal untuk master table (M_user_role)
-   folder app/Model
-   pembuatan : php artisan make:model Model/[Nama_model] -rm
-   ex : php artisan make:model Model/T_user_detail -rm

Database (migration):

-   selalu gunakan softdelete
-   jangan pernah pakai unique / foreign key di migration
-   pembuatan master table (untuk penggantian custom.php)
-   m\_(nama_table) -> m_user_roles, m_jenis_transaksis
-   untuk master dibuat seeder (prefer menggunakan updateOrCreate)
-   pembuatan table diawali dengan t\_
-   t\_(nama_table) -> t_users, t_transaksis
-   relation id menggunakan tipe data unsignedBigInteger
-   selalu buat migration baru jika ada perubahan / tambahan pada table (jangan edit yang lama)
-   update ERD

Datatable :

-   default -> server side
-   client side -> case jika kemungkinan data tidak lebih dari 1000

Controller :

-   Huruf Besar di awal, dilanjut dengan camel, tambah Controller
-   ex : TUserController, UserDetailController
-   untuk api, masuk folder Api, dengan tambahan nama ApiController
-   ex : Api/UserApiController, Api/TransaksiApiController
-   sebisa mungkin per model / modul punya controller sendiri

View :

-   nama folder plural (atau sama dengan nama table (dengan/tanpa t))
-   huruf kecil semua (tanpa kapital), spasi diganti underscore (\_)
-   sebisa mungkin view dibuat per model / modul
-   users / user_details
    -   index.blade.php
    -   create.blade.php
    -   edit.blade.php
    -   show.blade.php

Helper :

-   BE (php) -> app/Http/Helpers/Helpers.php
-   FE (js) -> public/assest/js/custom.js
-   untuk function yang digunakan terus menerus (banyak)
-   ex : format ribuan, format tanggal
