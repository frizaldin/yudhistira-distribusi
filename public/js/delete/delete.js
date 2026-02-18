const element = document.currentScript.getAttribute('element');

const confirmation = (id) => {
    Swal.fire({
        title: 'Anda yakin?',
        html: "<p style='margin-bottom: 0px;'>Data yang sudah dihapus tidak bisa dikembalikan!</p><small>Data pada yang berkaitan kemungkinan besar akan ikut terhapus.</small>",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Tidak'
    }).then((result) => {
        if (result.isConfirmed) {
            deleteProduct(id)
        }
    })
}

const deleteProduct = (id) => {
    showLoading()
    $.ajax({
        url: $(`.deleteForm-${id}`).attr('action'),
        type: 'POST',
        data: { id },
        success: function (e) {
            Swal.close()
            
            // Check if delete was successful (handle both success property and code 200)
            var isSuccess = (e.success === true) || (e.code === 200);
            
            if (isSuccess) {
                // Mark data as deleted immediately for visual feedback
                if (element === 'table') {
                    $(`#action-${id}`).html('<span class="badge bg-danger">Menghapus...</span>')
                    $(`#data-${id}`).css('background', '#dee2e6')
                    $(`#data-${id}`).css('opacity', '0.6')
                } else {
                    $(`#data-${id}`).remove()
                    if (e.folder == 'all') {
                        $("div[id*=data]").remove();
                    }
                }
                
                // Show success message briefly
                Swal.fire({
                    title: 'Berhasil!',
                    text: e.message || 'Data berhasil dihapus.',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 600,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                });
                
                // Force reload page immediately after short delay
                // Using multiple methods to ensure reload happens
                setTimeout(function() {
                    // Method 1: Force reload from server
                    window.location.reload(true);
                }, 600);
                
                // Backup: reload after 1 second if first one doesn't work
                setTimeout(function() {
                    window.location.href = window.location.href;
                }, 1000);
            } else {
                Swal.fire({
                    title: 'Gagal!',
                    text: e.message || 'Gagal menghapus data.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function (xhr, status, error) {
            Swal.close()
            var errorMessage = 'Terjadi kesalahan saat menghapus data.';
            var shouldReload = false;
            
            try {
                var err = JSON.parse(xhr.responseText);
                if (err.message) {
                    errorMessage = err.message;
                } else if (err.error) {
                    errorMessage = err.error;
                }
                // Check if response indicates data was already deleted
                if (err.message && (err.message.includes('sudah dihapus') || err.message.includes('tidak ditemukan'))) {
                    shouldReload = true;
                }
            } catch (e) {
                // If response is not JSON, use default message
                if (xhr.status === 404) {
                    errorMessage = 'Data tidak ditemukan atau sudah dihapus.';
                    shouldReload = true; // Reload if data not found (already deleted)
                } else if (xhr.status === 403) {
                    errorMessage = 'Anda tidak memiliki izin untuk menghapus data ini.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Terjadi kesalahan pada server.';
                }
            }
            
            Swal.fire({
                title: shouldReload ? 'Info' : 'Gagal!',
                text: errorMessage,
                icon: shouldReload ? 'info' : 'error',
                showConfirmButton: false,
                timer: shouldReload ? 1000 : 0
            }).then(() => {
                if (shouldReload) {
                    window.location.reload(true);
                }
            });
            
            // Backup reload if data was already deleted
            if (shouldReload) {
                setTimeout(function() {
                    window.location.reload(true);
                }, 1000);
            }
        }
    });
    return false
}
