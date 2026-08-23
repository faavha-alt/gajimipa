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

    table.meta { width: 100%; margin-bottom: 20px; }
    table.meta td { padding: 3px 0; font-size: 11px; vertical-align: top; }
    table.meta .label { width: 110px; color: #64748b; }

    .nominal-box { background: #f8fafc; border: 2px solid #1e293b; padding: 16px; text-align: center; margin-bottom: 16px; }
    .nominal-box .jenis { font-size: 12px; font-weight: bold; color: #1e293b; }
    .nominal-box .nominal { font-size: 22px; font-weight: bold; margin-top: 6px; }

    table.ket { width: 100%; border-collapse: collapse; }
    table.ket td { padding: 8px; border: 1px solid #e2e8f0; font-size: 10.5px; }
    table.ket .label { width: 110px; background: #f1f5f9; color: #475569; font-size: 9.5px; text-transform: uppercase; }

    .footer { margin-top: 28px; font-size: 9px; color: #94a3b8; text-align: center; }
    .revisi-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 8px; font-size: 9px; font-weight: bold; border-radius: 3px; margin-left: 6px; }
</style>
</head>
<body>
    <div class="header">
        @include('pdf.partials.kop-surat')
        <div class="judul">BUKTI POTONGAN @if($isRevisi ?? false)<span class="revisi-badge">REVISI</span>@endif</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Nomor Dokumen</td><td>: {{ $nomorDokumen }}</td>
        </tr>
        <tr>
            <td class="label">Nama</td><td>: {{ $nama }}</td>
        </tr>
        <tr>
            <td class="label">NIP</td><td>: {{ $nip }}</td>
        </tr>
        <tr>
            <td class="label">Periode</td><td>: {{ $period->nama_periode }}</td>
        </tr>
    </table>

    <div class="nominal-box">
        <div class="jenis">{{ $record->deductionType->nama }}</div>
        <div class="nominal">Rp{{ number_format($record->nominal, 0, ',', '.') }}</div>
    </div>

    <table class="ket">
        <tr>
            <td class="label">Keterangan</td>
            <td>{{ $record->keterangan ?: '-' }}</td>
        </tr>
    </table>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
        Dokumen administrasi internal — bukan bukti transfer/pembayaran bank.
    </div>
</body>
</html>
