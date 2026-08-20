<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    .header { text-align: center; border-bottom: 2px solid #1e293b; padding-bottom: 8px; margin-bottom: 12px; }
    .header .fakultas { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
    .header .univ { font-size: 10px; color: #475569; }
    .header .judul { font-size: 15px; font-weight: bold; margin-top: 8px; letter-spacing: 1px; }

    table.meta { width: 100%; margin-bottom: 14px; }
    table.meta td { padding: 1px 0; font-size: 10.5px; vertical-align: top; }
    table.meta .label { width: 90px; color: #64748b; }
    table.meta .sep { width: 10px; }
    table.meta .kanan { text-align: right; }

    table.rincian { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.rincian th { background: #f1f5f9; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.5px; color: #475569; padding: 5px 8px; border-bottom: 1px solid #cbd5e1; }
    table.rincian td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; font-size: 10.5px; }
    table.rincian td.nominal { text-align: right; }
    table.rincian tr.total td { font-weight: bold; background: #f8fafc; border-top: 1px solid #cbd5e1; }

    .bersih-pusat { background: #1e293b; color: #fff; padding: 7px 10px; font-size: 11px; font-weight: bold; margin-bottom: 10px; }
    .bersih-pusat .kanan { float: right; }

    .gaji-bersih { background: #f8fafc; border: 2px solid #1e293b; padding: 10px; margin-top: 6px; }
    .gaji-bersih .label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #475569; }
    .gaji-bersih .nominal { font-size: 18px; font-weight: bold; float: right; }

    .footer { margin-top: 24px; font-size: 9px; color: #94a3b8; text-align: center; }
    .revisi-badge { display: inline-block; background: #fef3c7; color: #92400e; padding: 2px 8px; font-size: 9px; font-weight: bold; border-radius: 3px; margin-left: 6px; }
</style>
</head>
<body>
    <div class="header">
        <div class="fakultas">FAKULTAS MATEMATIKA DAN ILMU PENGETAHUAN ALAM</div>
        <div class="univ">UNIVERSITAS SEBELAS MARET</div>
        <div class="judul">SLIP GAJI @if($isRevisi ?? false)<span class="revisi-badge">REVISI</span>@endif</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Nomor Dokumen</td><td>: {{ $nomorDokumen }}</td><td class="sep"></td>
            <td class="label">Periode</td><td>: {{ $period->nama_periode }}</td>
        </tr>
        <tr>
            <td class="label">Nama</td><td>: {{ $nama }}</td><td class="sep"></td>
            <td class="label">Unit</td><td>: {{ $record->unit_snapshot ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">NIP</td><td>: {{ $record->nip_snapshot }}</td><td class="sep"></td>
            <td class="label">Jabatan</td><td>: {{ $record->jabatan_snapshot ?? '-' }}</td>
        </tr>
    </table>

    <table class="rincian">
        <tr><th colspan="2">Penghasilan</th></tr>
        @forelse ($penghasilan as $item)
            <tr><td>{{ $item->nama_komponen }}</td><td class="nominal">Rp{{ number_format($item->nominal, 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="2" style="text-align:center;color:#94a3b8;">Tidak ada komponen bernilai &gt; 0.</td></tr>
        @endforelse
        <tr class="total"><td>Total Penghasilan Kotor</td><td class="nominal">Rp{{ number_format($record->total_penghasilan_kotor, 0, ',', '.') }}</td></tr>
    </table>

    <table class="rincian">
        <tr><th colspan="2">Potongan Pusat (BPJS, PFK, PPh, dll.)</th></tr>
        @forelse ($potonganPusat as $item)
            <tr><td>{{ $item->nama_komponen }}</td><td class="nominal">Rp{{ number_format($item->nominal, 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="2" style="text-align:center;color:#94a3b8;">Tidak ada komponen bernilai &gt; 0.</td></tr>
        @endforelse
        <tr class="total"><td>Total Potongan Pusat</td><td class="nominal">Rp{{ number_format($record->total_potongan_pusat, 0, ',', '.') }}</td></tr>
    </table>

    <div class="bersih-pusat">
        Bersih dari Pusat
        <span class="kanan">Rp{{ number_format($record->bersih_pusat, 0, ',', '.') }}</span>
        <div style="clear:both;"></div>
    </div>

    <table class="rincian">
        <tr><th colspan="2">Potongan Fakultas</th></tr>
        @forelse ($record->deductionRecords as $item)
            <tr><td>{{ $item->deductionType->nama }}</td><td class="nominal">Rp{{ number_format($item->nominal, 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="2" style="text-align:center;color:#94a3b8;">Tidak ada potongan fakultas.</td></tr>
        @endforelse
        <tr class="total"><td>Total Potongan Fakultas</td><td class="nominal">Rp{{ number_format($record->total_potongan_fakultas, 0, ',', '.') }}</td></tr>
    </table>

    <div class="gaji-bersih">
        <span class="label">Gaji Bersih</span>
        <span class="nominal">Rp{{ number_format($record->gaji_bersih_final, 0, ',', '.') }}</span>
        <div style="clear:both;"></div>
    </div>

    <div class="footer">
        Dokumen ini dibuat otomatis oleh Sistem Administrasi Gaji Fakultas MIPA UNS pada {{ now()->translatedFormat('d F Y H:i') }} WIB.
        Dokumen administrasi internal — bukan bukti transfer/pembayaran bank.
    </div>
</body>
</html>
