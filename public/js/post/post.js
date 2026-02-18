if (typeof ignoreConfirmation === "undefined") {
    var ignoreConfirmation =
        document.currentScript.getAttribute("ignoreConfirmation");
}

var $editors = $(".ckeditor");
if ($editors.length) {
    for (instance in ClassicEditor.instances) {
        ClassicEditor.instances[instance].updateElement();
    }
}

$("._form").submit(function (e) {
    e.preventDefault(); // tahan submit selalu

    let form = this;

    if (!form.checkValidity()) {
        e.stopPropagation();

        let invalidFields = [];

        $(form)
            .find(":invalid")
            .each(function () {
                let label = $(this).closest("div").find("label").text().trim();
                if (!label)
                    label =
                        $(this).attr("name") || this.id || "Field tanpa nama";
                invalidFields.push(label);
            });

        Swal.fire({
            icon: "error",
            title: "Form Belum Lengkap",
            html: `
                <div style="text-align:left;">
                    <b>Field berikut belum diisi / salah:</b>
                    <ul>
                        ${invalidFields.map((f) => `<li>${f}</li>`).join("")}
                    </ul>
                </div>
            `,
        }).then(() => {
            $(form).find(":invalid").first().focus();
        });

        return false; // 🔥 PENTING: stop biar gak lanjut ke Swal konfirmasi
    }

    const run = () => {
        showLoading();

        $.ajax({
            url: form.action,
            type: "POST",
            data: new FormData(form),
            processData: false,
            contentType: false,
            cache: false,
            success: function (res) {
                Swal.close();
                Swal.fire({
                    title: res.success ? "Berhasil!" : "Gagal!",
                    text: res.message,
                    icon: res.success ? "success" : "error",
                    showConfirmButton: false,
                    timer: res.success ? 1500 : 10000,
                });

                if (res.url) window.location.replace(res.url);
                if (res.reload) window.location.reload();
            },
            error: function (xhr) {
                Swal.close();
                let err = JSON.parse(xhr.responseText || "{}");
                Swal.fire({
                    title: "Gagal!",
                    text: err.message || "Terjadi kesalahan",
                    icon: "error",
                });
            },
        });

        return false;
    };

    if (ignoreConfirmation) {
        run();
    } else {
        Swal.fire({
            title: "Apakah data anda sudah benar?",
            text: "Pastikan data yang anda masukan sudah benar untuk di simpan",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Ya, data sudah benar",
            cancelButtonText: "Tidak, saya akan cek lagi",
        }).then((result) => {
            if (result.isConfirmed) {
                run();
            }
        });
    }
});
