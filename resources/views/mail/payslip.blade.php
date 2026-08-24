<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #1e293b;">
    <p>Kepada Yth. <strong>{{ $payslip->salaryRecord?->employee?->nama ?? $payslip->salaryRecord?->nama_snapshot ?? 'Pegawai' }}</strong>,</p>

    <p>Berikut kami sampaikan <strong>Slip Gaji {{ $payslip->salaryRecord?->salaryPeriod?->nama_periode }}</strong>
    (Nomor Dokumen: <strong>{{ $payslip->nomor_dokumen }}</strong>) dalam lampiran PDF.</p>

    <p>Dokumen ini dihasilkan otomatis oleh <em>Sistem Administrasi Gaji Fakultas MIPA UNS</em>.
    Mohon tidak membalas email ini.</p>
</div>
