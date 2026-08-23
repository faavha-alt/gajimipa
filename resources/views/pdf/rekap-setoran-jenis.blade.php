<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 16px; }
    .header .fakultas { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
    .header .univ { font-size: 10px; color: #475569; }
    .header .alamat { font-size: 9px; color: #64748b; margin-top: 1px; }
    .header .judul { font-size: 15px; font-weight: bold; margin-top: 8px; letter-spacing: 1px; }
    .header .periode { font-size: 11px; color: #475569; margin-top: 2px; }

    table.rekap { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.rekap th { background: #f1f5f9; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; padding: 6px 8px; border-bottom: 1px solid #cbd5e1; }
    table.rekap td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }
    table.rekap td.num { text-align: right; }
    table.rekap tr.total td { font-weight: bold; background: #f8fafc; border-top: 2px solid #1e293b; }

    .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.kop-surat')
        <div class="judul">REKAP SETORAN POTONGAN</div>
        <div class="periode">{{ $period->nama_periode }}</div>
    </div>

    <table class="rekap">
        <tr>
            <th>Jenis Potongan</th>
            <th class="num">Jumlah Pegawai</th>
            <th class="num">Total</th>
        </tr>
        @forelse ($rekap as $row)
            <tr>
                <td>{{ $row['nama'] }}</td>
                <td class="num">{{ $row['jumlah_pegawai'] }}</td>
                <td class="num">Rp{{ number_format($row['total_nominal'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align:center;color:#94a3b8;">Tidak ada data potongan pada periode ini.</td></tr>
        @endforelse
        <tr class="total">
            <td>Total Keseluruhan</td>
            <td class="num">{{ $rekap->sum('jumlah_pegawai') }}</td>
            <td class="num">Rp{{ number_format($rekap->sum('total_nominal'), 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
        Rekap ini bukan bukti transfer/pembayaran — dipakai untuk proses setoran di luar aplikasi.
    </div>
</body>
</html>
