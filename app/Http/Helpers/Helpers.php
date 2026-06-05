<?php

function ribuan($angka){

    $hasil_ribuan = number_format($angka,2,'.',',');
    return $hasil_ribuan;

}

function tgl_ymd($tanggal){

    $tanggal_ymd = date('Y-m-d', strtotime($tanggal));
    return $tanggal_ymd;

}



