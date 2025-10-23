<?php

namespace App\Exports;

use App\Models\ChannelBalance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BalancesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;
    protected $channelId;

    public function __construct($startDate, $endDate, $channelId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->channelId = $channelId;
    }

    public function query()
    {
        $query = ChannelBalance::with('channel')
            ->whereBetween('date', [
                $this->startDate,
                $this->endDate
            ]);

        if ($this->channelId) {
            $query->where('channel_id', $this->channelId);
        }

        return $query->orderBy('date', 'desc')
                     ->orderBy('channel_id')
                     ->orderBy('currency');
    }

    public function headings(): array
    {
        return [
            '日期',
            '支付渠道',
            '货币类型',
            '初始金额',
            '当日入账',
            '当日出账',
            '净流入',
            '当前余额',
        ];
    }

    public function map($balance): array
    {
        return [
            $balance->date,
            $balance->channel->name ?? '',
            $balance->currency === 'RMB' ? '人民币' : '港币',
            $balance->initial_amount,
            $balance->income_amount,
            $balance->outcome_amount,
            $balance->income_amount - $balance->outcome_amount,
            $balance->current_balance,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 设置标题行样式
        $sheet->getStyle('A1:H1')->applyFromArray([
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
        $sheet->getStyle('A2:H' . ($sheet->getHighestRow()))->applyFromArray([
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
        $sheet->getStyle('D:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}
