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
    .summary td { width: 25%; padding: 8px; text-align: center; border: 1px solid #e2e8f0; }
    .summary .label { font-size: 8.5px; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .summary .nilai { font-size: 11px; font-weight: bold; margin-top: 3px; }

    h3.section { font-size: 11px; font-weight: bold; margin: 16px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; }

    table.rekap { width: 100%; border-collapse: collapse; }
    table.rekap th { background: #f1f5f9; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; padding: 5px 7px; border-bottom: 1px solid #cbd5e1; }
    table.rekap td { padding: 4px 7px; border-bottom: 1px solid #e2e8f0; font-size: 9.5px; }
    table.rekap td.num { text-align: right; }
    table.rekap tr.total td { font-weight: bold; background: #f8fafc; border-top: 1px solid #cbd5e1; }

    .bar-track { background: #f1f5f9; height: 10px; width: 100px; }
    .bar-fill { background: #4f46e5; height: 10px; }

    .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.kop-surat')
        <div class="judul">LAPORAN TAHUNAN GAJI</div>
        <div class="periode">Tahun {{ $tahun }}</div>
    </div>

    <table class="summary">
        <tr>
            <td><div class="label">Penghasilan Kotor</div><div class="nilai">Rp{{ number_format($totals['total_penghasilan_kotor'], 0, ',', '.') }}</div></td>
            <td><div class="label">Potongan Pusat</div><div class="nilai">Rp{{ number_format($totals['total_potongan_pusat'], 0, ',', '.') }}</div></td>
            <td><div class="label">Potongan Fakultas</div><div class="nilai">Rp{{ number_format($totals['total_potongan_fakultas'], 0, ',', '.') }}</div></td>
            <td><div class="label">Gaji Bersih</div><div class="nilai">Rp{{ number_format($totals['total_gaji_bersih'], 0, ',', '.') }}</div></td>
        </tr>
    </table>

    <h3 class="section">Perbandingan Antarbulan</h3>
    <table class="rekap">
        <tr>
            <th>Bulan</th><th class="num">Jumlah Pegawai</th><th class="num">Penghasilan Kotor</th><th class="num">Gaji Bersih</th><th>Proporsi Gaji Bersih</th>
        </tr>
        @php $maxBersih = $perBulan->max('total_gaji_bersih') ?: 1; @endphp
        @forelse ($perBulan as $row)
            <tr>
                <td>{{ $row['nama_bulan'] }} (v{{ $row['periode']->versi }})</td>
                <td class="num">{{ $row['jumlah_pegawai'] }}</td>
                <td class="num">Rp{{ number_format($row['total_penghasilan_kotor'], 0, ',', '.') }}</td>
                <td class="num">Rp{{ number_format($row['total_gaji_bersih'], 0, ',', '.') }}</td>
                <td>
                    <div class="bar-track"><div class="bar-fill" style="width: {{ max(2, round($row['total_gaji_bersih'] / $maxBersih * 100)) }}px;"></div></div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#94a3b8;">Belum ada periode FINAL/ARSIP pada tahun ini.</td></tr>
        @endforelse
        <tr class="total">
            <td colspan="2">Total Tahun {{ $tahun }}</td>
            <td class="num">Rp{{ number_format($totals['total_penghasilan_kotor'], 0, ',', '.') }}</td>
            <td class="num">Rp{{ number_format($totals['total_gaji_bersih'], 0, ',', '.') }}</td>
            <td></td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
        Hanya periode berstatus FINAL/ARSIP versi aktif (bukan digantikan) yang dihitung.
    </div>
</body>
</html>
