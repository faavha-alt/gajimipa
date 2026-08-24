<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1e293b; }
    .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 16px; }
    .header .fakultas { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
    .header .univ { font-size: 10px; color: #475569; }
    .header .alamat { font-size: 9px; color: #64748b; margin-top: 1px; }
    .header .judul { font-size: 15px; font-weight: bold; margin-top: 8px; letter-spacing: 1px; }
    .header .periode { font-size: 11px; color: #475569; margin-top: 2px; }

    .summary { width: 100%; margin-bottom: 16px; }
    .summary td { width: 20%; padding: 8px; text-align: center; border: 1px solid #e2e8f0; }
    .summary .label { font-size: 8.5px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .summary .nilai { font-size: 11px; font-weight: bold; margin-top: 3px; }

    h3.section { font-size: 11px; font-weight: bold; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; }

    table.rekap { width: 100%; border-collapse: collapse; }
    table.rekap th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; padding: 5px 7px; border-bottom: 1px solid #cbd5e1; }
    table.rekap td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
    table.rekap td.num { text-align: right; }
    table.rekap tr.total td { font-weight: bold; background: #f8fafc; border-top: 1px solid #cbd5e1; }

    .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.kop-surat')
        <div class="judul">LAPORAN BULANAN GAJI</div>
        <div class="periode">{{ $period->nama_periode }}</div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Jumlah Pegawai</div><div class="nilai">{{ $totals['jumlah_pegawai'] }}</div></td>
            <td><div class="label">Penghasilan Kotor</div><div class="nilai">Rp{{ number_format($totals['total_penghasilan_kotor'], 0, ',', '.') }}</div></td>
            <td><div class="label">Potongan Pusat</div><div class="nilai">Rp{{ number_format($totals['total_potongan_pusat'], 0, ',', '.') }}</div></td>
            <td><div class="label">Potongan Fakultas</div><div class="nilai">Rp{{ number_format($totals['total_potongan_fakultas'], 0, ',', '.') }}</div></td>
            <td><div class="label">Gaji Bersih</div><div class="nilai">Rp{{ number_format($totals['total_gaji_bersih'], 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <h3 class="section">Rekap per Unit</h3>
    <table class="rekap">
        <tr><th scope="col">Unit</th><th scope="col" class="num">Jumlah Pegawai</th><th scope="col" class="num">Penghasilan Kotor</th><th scope="col" class="num">Gaji Bersih</th></tr>
        @forelse ($perUnit as $unit => $row)
            <tr>
                <td>{{ $unit }}</td>
                <td class="num">{{ $row['jumlah_pegawai'] }}</td>
                <td class="num">Rp{{ number_format($row['total_penghasilan_kotor'], 0, ',', '.') }}</td>
                <td class="num">Rp{{ number_format($row['total_gaji_bersih'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;color:#94a3b8;">Tidak ada data.</td></tr>
        @endforelse
    </table>

    <h3 class="section">Rekap per Jenis Potongan</h3>
    <table class="rekap">
        <tr><th scope="col">Jenis Potongan</th><th scope="col" class="num">Jumlah Pegawai</th><th scope="col" class="num">Total</th></tr>
        @forelse ($perJenisPotongan as $row)
            <tr>
                <td>{{ $row['nama'] }}</td>
                <td class="num">{{ $row['jumlah_pegawai'] }}</td>
                <td class="num">Rp{{ number_format($row['total_nominal'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align:center;color:#94a3b8;">Tidak ada data potongan.</td></tr>
        @endforelse
    </table>

    <h3 class="section">Daftar Pegawai</h3>
    <table class="rekap">
        <tr>
            <th scope="col">NIP</th><th scope="col">Nama</th><th scope="col">Unit</th>
            <th scope="col" class="num">Penghasilan Kotor</th><th scope="col" class="num">Pot. Pusat</th><th scope="col" class="num">Pot. Fakultas</th><th scope="col" class="num">Gaji Bersih</th>
        </tr>
        @foreach ($pegawai as $r)
            <tr>
                <td>{{ $r->nip_snapshot }}</td>
                <td>{{ $r->employee?->nama ?? $r->nama_snapshot }}</td>
                <td>{{ $r->unit_snapshot ?: '-' }}</td>
                <td class="num">Rp{{ number_format($r->total_penghasilan_kotor, 0, ',', '.') }}</td>
                <td class="num">Rp{{ number_format($r->total_potongan_pusat, 0, ',', '.') }}</td>
                <td class="num">Rp{{ number_format($r->total_potongan_fakultas, 0, ',', '.') }}</td>
                <td class="num">Rp{{ number_format($r->gaji_bersih_final, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="3">Total Keseluruhan</td>
            <td class="num">Rp{{ number_format($totals['total_penghasilan_kotor'], 0, ',', '.') }}</td>
            <td class="num">Rp{{ number_format($totals['total_potongan_pusat'], 0, ',', '.') }}</td>
            <td class="num">Rp{{ number_format($totals['total_potongan_fakultas'], 0, ',', '.') }}</td>
            <td class="num">Rp{{ number_format($totals['total_gaji_bersih'], 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
    </div>
</body>
</html>
