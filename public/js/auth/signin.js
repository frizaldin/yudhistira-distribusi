$(document).ready(function () {
    $("#_loginForm").validate({
        rules: {
            email: { required: true, email: true }
        },
        submitHandler: function (form) {
            showLoading();  // Menampilkan loading dengan SweetAlert2 dan TailChase

            $.ajax({
                url: form.action,
                type: form.method,
                data: $(form).serialize(),
                success: function (e) {
                    hideLoading();  // Menyembunyikan loading setelah selesai

                    if (e.success) {
                        alertSuccess('Berhasil', e.message || 'Anda Akan Di Arahkan Ke Halaman Dashboard!');
                        setTimeout(() => {
                            window.location.href = e.url;
                        }, 1000);
                    } else {
                        alertError('Berhasil', e.message || 'Gagal!');
                    }
                },
                error: function (xhr, status, error) {
                    hideLoading();  // Menyembunyikan loading

                    let err = {};
                    try {
                        err = JSON.parse(xhr.responseText);
                    } catch (e) {
                        err.message = 'Terjadi kesalahan tak dikenal.';
                    }

                    alertError('Gagal', err.message || 'Terjadi error!');
                }
            });
        }
    });
});
