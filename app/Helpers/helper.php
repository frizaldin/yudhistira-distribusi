<?php

function errors($th, $message = null)
{
    return [
        'code' => 500,
        'success' => false,
        'message' => $message ?? $th->getMessage(),
        'line' => $th->getLine(),
        'file' => $th->getFile()
    ];
}

if (!function_exists('getImage')) {
    function getImage($path, $default = 'no-image.png')
    {
        if ($path && file_exists(env('BASE_PATH') . $path)) {
            return env('BASE_URL') . ($path);
        }

        return asset($default);
    }
}

if (!function_exists('languageText')) {
    function languageText($en, $id)
    {
        $lang = session('language');
        if ($lang !== null && $lang === 'en') {
            return $en;
        }
        return $id;
    }
}

if (!function_exists('badgeUserRole')) {
    function badgeUserRole($roleId)
    {
        $roles = [
            1 => ['Super Admin', 'badge bg-dark text-white'],
            2 => ['Supplier', 'badge bg-primary text-white'],
            3 => ['Logistik', 'badge bg-info text-dark'],
            4 => ['Pelaksana', 'badge bg-success text-white'],
            5 => ['Keuangan', 'badge bg-warning text-dark'],
            6 => ['Perencanaan', 'badge bg-secondary text-white'],
            7 => ['Direktur Utama', 'badge bg-danger text-white'],
            8 => ['Customer', 'badge bg-light text-dark border'],
            9 => ['Direktur Tenik', 'badge bg-danger text-white'],
            10 => ['Direktur Operasional', 'badge bg-danger text-white'],
        ];

        if (isset($roles[$roleId])) {
            [$role, $class] = $roles[$roleId];
            return '<span class="' . $class . '">' . htmlspecialchars($role) . '</span>';
        }

        return '<span class="badge bg-light text-dark border">Unknown</span>';
    }
}

if (!function_exists('urlRole')) {
    function urlRole($id)
    {
        if ($id == 1) {
            return url('user/superadmin');
        } elseif ($id == 2) {
            return url('user/supplier');
        } elseif ($id == 3) {
            return url('user/logistik');
        } elseif ($id == 4) {
            return url('user/pelaksana');
        } elseif ($id == 5) {
            return url('user/keuangan');
        } elseif ($id == 6) {
            return url('user/perencanaan');
        } elseif ($id == 7) {
            return url('user/direksi');
        } elseif ($id == 8) {
            return url('user/customer');
        }
    }
}

function priceToInt($price)
{
    return str_replace(',', '', $price);
}



if (!function_exists('dateFormated')) {
    function dateFormated($date)
    {
        return $date ? date('d F, Y', strtotime($date)) : '-';
    }
}

if (!function_exists('priceRp')) {
    function priceRp($price)
    {
        return 'Rp ' . number_format((float)$price, 0, '.', ',
        ');
    }
}

if (!function_exists('month')) {
    function month($num = null)
    {
        // Jawaban: Tidak benar, karena mendefinisikan key array dengan angka nol di depan (misal 01, 02, dst) akan menyebabkan error di PHP versi terbaru (invalid numeric literal).
        // Selain itu, key 01, 02, dst akan dianggap sama dengan 1, 2, dst oleh PHP, sehingga terjadi duplikasi key dan tidak ada gunanya.
        // Cukup gunakan 1-12 saja, seperti berikut:
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        if ($num === null) {
            return $months;
        }

        return $months[(int)$num] ?? null;
    }
}

if (!function_exists('rating')) {
    function rating($score)
    {
        if ($score > 95) {
            return 5;
        } elseif ($score >= 90) {
            return 4;
        } elseif ($score >= 80) {
            return 3;
        } elseif ($score >= 70) {
            return 2;
        } else {
            return 1;
        }
    }
}
if (!function_exists('ratingStar')) {
    function ratingStar($rating, $max = 5)
    {
        $rating = (int)$rating;
        $max = (int)$max;
        $stars = '';
        for ($i = 1; $i <= $max; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="fa-solid fa-star text-warning"></i>';
            } else {
                $stars .= '<i class="fa-solid fa-star text-secondary"></i>';
            }
        }
        return $stars;
    }
}
