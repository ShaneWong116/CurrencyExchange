<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $channelId;
    protected $type;

    public function __construct($startDate, $endDate, $channelId = null, $type = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->channelId = $channelId;
        $this->type = $type;
    }

    public function query()
    {
        $query = Transaction::with(['user', 'channel'])
            ->whereBetween('created_at', [
                $this->startDate,
                $this->endDate . ' 23:59:59'
            ]);

        if ($this->channelId) {
            $query->where('channel_id', $this->channelId);
        }

        if ($this->type) {
            $query->where('type', $this->type);
        }

        return $query->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            '交易ID',
            '交易号',
            '外勤人员',
            '交易类型',
            '人民币金额',
            '港币金额',
            '交易汇率',
            '即时买断汇率',
            '支付渠道',
            '交易地点',
            '状态',
            '备注',
            '提交时间',
            '创建时间',
        ];
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->uuid,
            $transaction->user->name ?? '',
            $this->getTypeLabel($transaction->type),
            $transaction->rmb_amount,
            $transaction->hkd_amount,
            $transaction->exchange_rate,
            $transaction->instant_rate,
            $transaction->channel->name ?? '',
            $transaction->location,
            $this->getStatusLabel($transaction->status),
            $transaction->notes,
            $transaction->submit_time->format('Y-m-d H:i:s'),
            $transaction->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 设置标题行样式
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '366092'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ]);

        // 设置数据区域样式
        $sheet->getStyle('A2:N' . ($sheet->getHighestRow()))->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
            ],
        ]);

        // 设置金额列右对齐
        $sheet->getStyle('E:F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('G:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }

    private function getTypeLabel(string $type): string
    {
        return match($type) {
            'income' => '入账',
            'outcome' => '出账',
            'instant_buyout' => '即时买断',
            'exchange' => '兑换',
            default => $type
        };
    }

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending' => '处理中',
            'success' => '成功',
            'failed' => '失败',
            default => $status
        };
    }
}
