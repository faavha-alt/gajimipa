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

    .bank-title { background: #1e293b; color: #fff; padding: 6px 8px; font-size: 11px; font-weight: bold; margin-top: 16px; }

    table.rekap { width: 100%; border-collapse: collapse; }
    table.rekap th { background: #f1f5f9; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; padding: 5px 8px; border-bottom: 1px solid #cbd5e1; }
    table.rekap td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
    table.rekap td.num { text-align: right; }
    table.rekap tr.subtotal td { font-weight: bold; background: #f8fafc; border-top: 1px solid #cbd5e1; }

    .grand-total { margin-top: 16px; background: #f8fafc; border: 2px solid #1e293b; padding: 10px; }
    .grand-total .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
    .grand-total .nominal { font-size: 16px; font-weight: bold; float: right; }

    .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.kop-surat')
        <div class="judul">REKAP SETORAN POTONGAN PER BANK</div>
        <div class="periode">{{ $period->nama_periode }}</div>
    </div>

    @forelse ($perBank as $namaBank => $baris)
        <div class="bank-title">{{ $namaBank }} — {{ $baris->count() }} pegawai</div>
        <table class="rekap">
            <tr>
                <th scope="col">NIP</th>
                <th scope="col">Nama</th>
                <th scope="col">Nama Rekening</th>
                <th scope="col">No. Rekening</th>
                <th scope="col" class="num">Total Potongan</th>
            </tr>
            @foreach ($baris as $row)
                <tr>
                    <td>{{ $row['nip'] }}</td>
                    <td>{{ $row['nama'] }}</td>
                    <td>{{ $row['nama_rekening'] ?? '-' }}</td>
                    <td>{{ $row['no_rekening'] ?? '-' }}</td>
                    <td class="num">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="4">Subtotal {{ $namaBank }}</td>
                <td class="num">Rp {{ number_format($baris->sum('total'), 0, ',', '.') }}</td>
            </tr>
        </table>
    @empty
        <p style="text-align:center;color:#94a3b8;margin-top:16px;">Tidak ada data potongan pada periode ini.</p>
    @endforelse

    <div class="grand-total">
        Total Keseluruhan
        <span class="nominal">Rp {{ number_format($perBank->flatten(1)->sum('total'), 0, ',', '.') }}</span>
        <div style="clear:both;"></div>
    </div>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
        Rekap ini bukan bukti transfer/pembayaran — dipakai untuk proses tarik tunai/setoran di bank di luar aplikasi.
    </div>
</body>
</html>
