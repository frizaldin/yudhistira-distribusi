function alertSuccess(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'success',
        confirmButtonText: 'OK'
    });
}

function alertError(title, message) {
    Swal.fire({
        title: title,
        text: message,
        icon: 'error',
        confirmButtonText: 'Ok'
    });
}

function showLoading(message = 'Tunggu proses selesai...') {
    Swal.fire({
        title: 'Loading...',
        html: `
            <div style="text-align: center;">
                <l-tail-chase size="40" speed="1.75" color="#dfc620"></l-tail-chase>
                <p>${message}</p>
            </div>
        `,
        showConfirmButton: false,  // Hide the confirm button
        willOpen: () => {
            // Memastikan TailChase bisa ditampilkan dengan benar
            const tailChase = document.querySelector('l-tail-chase');
            tailChase && tailChase.setAttribute('size', '40');
        }
    });
}

function hideLoading() {
    Swal.close();  // Menutup loading tanpa menyimpan instancenya
}

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

// Ready Document Function
$(document).ready(function () {
    // Exclude select dengan data-no-select2 dan select2-modal dari inisialisasi select2
    $('select').not('[data-no-select2="true"]').not('.select2-modal').select2({
        theme: 'bootstrap4',
        width: '100%'
    });

    $('.price').mask('000,000,000,000,000,000,000,000', {
        reverse: true
    });

    $('input[type="file"]').change(function (e) {
        var file = e.target.files[0]; // Ambil file pertama

        // Cari elemen img setelah input file, lalu hapus kalau ada
        $(this).next('img').remove();

        if (file) {
            var reader = new FileReader(); // Buat FileReader
            reader.onload = function (e) {
                // Buat elemen img baru
                var newImg = $('<img>')
                    .attr('src', e.target.result)
                    .css({
                        'max-width': '100px',
                        'display': 'block'
                    });

                // Tambahin elemen img setelah input file
                $(this).after(newImg);
            }.bind(this); // Bind this ke fungsi reader.onload

            reader.readAsDataURL(file); // Baca file sebagai DataURL

            // Update nama file di label
            $(this).next('.custom-file-label').html(file.name);
        }
    });
    // Aktifkan tooltip Bootstrap 5 untuk elemen dengan atribut title
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('.btn[title]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const tooltipTriggerListSpan = [].slice.call(document.querySelectorAll('.notif-span-button[title]'));
    tooltipTriggerListSpan.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Exclude select dengan data-no-select2 dan select2-modal dari inisialisasi select2
    $('select').not('[data-no-select2="true"]').not('.select2-modal').select2();

    $('.hide-toggle').on('click', function () {
        target = $(this).data('target');
        const $target = $('#' + target);
        if ($target.hasClass('d-none')) {
            $target.removeClass('d-none').hide().slideDown(1000);
        } else {
            $target.slideUp(1000, function () {
                $target.addClass('d-none');
            });
        }
        $(this).find('i').toggleClass('fa-minus fa-plus');
        $(this).attr('title', function (i, title) {
            return title === 'Tampilkan' ? 'Sembunyikan' : 'Tampilkan';
        });
    });

    // Jangan inisialisasi select2-modal di sini, biarkan diinisialisasi saat modal dibuka
    // Ini untuk menghindari masalah dengan modal yang belum ter-render
    $('.select2-modal').each(function () {
        // Destroy select2 yang mungkin sudah diinisialisasi oleh $('select').select2() di atas
        if ($(this).hasClass('select2-hidden-accessible')) {
            try {
                $(this).select2('destroy');
            } catch(e) {
                // Ignore error
            }
        }
    });
});

$('.btn').addClass('btn-sm'); // Tambahkan kelas btn-sm ke semua tombol
$('.btn').removeClass('mb-1 mb-2 mb-3 mb-4 mb-5'); // Hapus kelas margin bawah dari semua tombol

