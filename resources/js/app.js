import './axios';

import * as bootstrap from 'bootstrap';
import Swal from 'sweetalert2';
import TomSelect from 'tom-select';
import Chart from 'chart.js/auto';

// Diekspos ke window agar bisa dipakai di script per halaman (@push('scripts'))
window.bootstrap = bootstrap;
window.Swal = Swal;
window.TomSelect = TomSelect;
window.Chart = Chart;

/* ---------------------------------------------------------
   Helper format angka (sama dengan helper PHP rupiah() & qty())
   --------------------------------------------------------- */
window.formatRupiah = (angka, prefix = true) => {
    const n = Math.round(Number(angka) || 0);
    const hasil = Math.abs(n).toLocaleString('id-ID');
    return (n < 0 ? '-' : '') + (prefix ? 'Rp ' : '') + hasil;
};

window.formatQty = (angka) =>
    (Number(angka) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });

/** Teks uang "9.965.000" / "12.345,67" -> angka 9965000 / 12345.67 */
window.parseUang = (s) => Number(String(s ?? '').replace(/[^\d,]/g, '').replace(',', '.')) || 0;

/** Teks qty "12,5" / "12.5" -> angka 12.5 */
window.parseQty = (s) => Number(String(s ?? '').trim().replace(',', '.')) || 0;

/** <input class="input-uang">: tampil dengan titik ribuan saat diketik (64000 -> 64.000) */
window.formatInputUang = (el) => {
    if (el.readOnly) return;
    const digit = el.value.replace(/\D/g, '');
    el.value = digit ? Number(digit).toLocaleString('id-ID') : '';
};
document.addEventListener('input', (e) => {
    if (e.target.classList?.contains('input-uang')) window.formatInputUang(e.target);
});

/* ---------------------------------------------------------
   Toast notifikasi
   --------------------------------------------------------- */
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});
window.Toast = Toast;

document.addEventListener('DOMContentLoaded', () => {
    // Tampilkan flash message dari session sebagai toast
    const flash = document.getElementById('flash-data');
    if (flash) {
        if (flash.dataset.success) Toast.fire({ icon: 'success', title: flash.dataset.success });
        if (flash.dataset.error) Toast.fire({ icon: 'error', title: flash.dataset.error, timer: 6000 });
        if (flash.dataset.warning) Toast.fire({ icon: 'warning', title: flash.dataset.warning });
    }

    // Tom Select otomatis untuk <select class="tom-select">
    document.querySelectorAll('select.tom-select').forEach((el) => {
        if (el.tomselect) return;
        const multi = el.multiple;
        new TomSelect(el, {
            allowEmptyOption: !multi,
            create: false,
            placeholder: el.getAttribute('placeholder') || 'Cari...',
            maxOptions: 1000,
            // Multi pilih (filter klien/supplier): tombol x di tiap pilihan, dropdown tetap terbuka
            plugins: multi ? ['remove_button'] : [],
            closeAfterSelect: !multi,
            ...(multi ? { hidePlaceholder: true } : {}),
        });
    });

    // Tabel daftar → tampilan kartu di HP (lihat .tabel-kartu di app.css)
    document.querySelectorAll('.table-responsive > table.table:not(.tabel-item)').forEach(siapkanKartuMobile);

    // Tooltip Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
});

/* ---------------------------------------------------------
   Tabel responsif: setiap sel diberi label dari judul kolomnya,
   sehingga di HP baris tampil sebagai kartu "Label .... Nilai".
   Opsional: <table data-mobile-judul="2"> = kolom ke-2 jadi judul kartu (default kolom 1).
   --------------------------------------------------------- */
function siapkanKartuMobile(table) {
    if (table.dataset.kartuSiap) return;
    table.dataset.kartuSiap = '1';
    table.classList.add('tabel-kartu');

    const judulKolom = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    const indeksJudul = parseInt(table.dataset.mobileJudul || '1', 10) - 1;

    table.querySelectorAll('tbody tr, tfoot tr').forEach((tr) => {
        let kolom = 0;
        [...tr.children].forEach((sel) => {
            const span = sel.colSpan || 1;
            const label = judulKolom[kolom] || '';

            if (span > 1) {
                sel.classList.add('sel-penuh');
            } else if (!label || label.toLowerCase() === 'aksi') {
                sel.classList.add('sel-aksi');
            } else {
                sel.dataset.label = label;
                if (kolom === indeksJudul && tr.parentElement.tagName === 'TBODY') sel.classList.add('sel-judul');
            }

            // Sel kosong (mis. kolom Masuk/Keluar yang tidak terisi) disembunyikan di HP
            if (!sel.textContent.trim() && !sel.querySelector('a, button, form, input, img')) {
                sel.classList.add('sel-kosong');
            }

            // Bungkus isi sel agar label & nilai tersusun rapi berdampingan
            const nilai = document.createElement('div');
            nilai.className = 'sel-nilai';
            while (sel.firstChild) nilai.appendChild(sel.firstChild);
            sel.appendChild(nilai);

            kolom += span;
        });
    });
}

const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* Konfirmasi pulihkan data dari Riwayat Hapus */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.classList.contains('form-pulihkan') || form.dataset.confirmed === '1') return;

    e.preventDefault();
    Swal.fire({
        title: 'Pulihkan data?',
        html: `<b>${escapeHtml(form.dataset.nama || 'Data ini')}</b> akan dikembalikan.<br><small class="text-muted">Stok, HPP, cashflow & status bayar dihitung ulang otomatis.</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-arrow-counterclockwise"></i> Ya, pulihkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: { confirmButton: 'btn btn-success ms-2', cancelButton: 'btn btn-light' },
        buttonsStyling: false,
    }).then((result) => {
        if (result.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});

/* ---------------------------------------------------------
   Konfirmasi hapus dengan SweetAlert2
   Pemakaian: <form method="POST" class="form-hapus" data-nama="Semen Gresik"> ... </form>
   --------------------------------------------------------- */
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!form.classList.contains('form-hapus') || form.dataset.confirmed === '1') return;

    e.preventDefault();
    const nama = form.dataset.nama ? `<b>${escapeHtml(form.dataset.nama)}</b>` : 'data ini';
    const catatan = form.dataset.permanen
        ? 'Dihapus permanen, tidak bisa dipulihkan lagi.'
        : 'Data masuk ke <b>Riwayat Hapus</b> dan bisa dipulihkan kapan saja.';

    Swal.fire({
        title: form.dataset.permanen ? 'Hapus permanen?' : 'Hapus data?',
        html: `Anda yakin ingin menghapus ${nama}?<br><small class="text-muted">${catatan}</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-trash"></i> Ya, hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        customClass: { confirmButton: 'btn btn-danger ms-2', cancelButton: 'btn btn-light' },
        buttonsStyling: false,
    }).then((result) => {
        if (result.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});
