// Ambil ignoreConfirmation hanya jika belum ada sebelumnya
if (typeof ignoreConfirmation === 'undefined') {
    var ignoreConfirmation = document.currentScript.getAttribute('ignoreConfirmation');
}

var $editors = $(".ckeditor");
if ($editors.length) {
    for (instance in ClassicEditor.instances) {
        ClassicEditor.instances[instance].updateElement();
    }
}

$('._status').submit(function (e) {
    e.preventDefault()

    const run = (e) => {
        showLoading()

        $.ajax({
            url: this.action,
            type: "POST",
            data: new FormData(this),
            processData: false,
            contentType: false,
            cache: false,
            success: function (e) {
                console.log(e);
                Swal.close()
                Swal.fire({
                    title: (e.success == true) ? 'Berhasil!' : 'Gagal!',
                    text: e.message,
                    icon: (e.success == true) ? 'success' : 'error',
                    showConfirmButton: false,
                    timer: (e.success == true) ? 1000 : 10000
                });


                if (e.url) {
                    window.location.href = e.url;
                }
                if (e.success == true) {
                    window.location.reload();
                }
                if ($('.modal.show').length) {
                    // Set aria-hidden to false before hiding to avoid accessibility issues
                    $('.modal.show').attr('aria-hidden', 'false');
                    $('.modal.show').modal('hide');
                }
            },
            error: function (xhr, status, error) {
                Swal.close()
                var err = eval("(" + xhr.responseText + ")");
                Swal.fire({
                    title: 'Gagal!',
                    text: err.message,
                    icon: 'error',
                    buttons: {
                        cancel: "Tutup",
                    },
                });
            }
        });
        return false;
    };

    if (ignoreConfirmation) {
        run(e)
    } else {
        Swal.fire({
            title: 'Ubah Status?',
            text: "Apakah Anda yakin ingin mengubah status ini?",
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, data sudah benar',
            cancelButtonText: 'Tidak, saya akan cek lagi'
        }).then((result) => {
            if (result.isConfirmed) {
                run(e)
            }
        })
    }

})
