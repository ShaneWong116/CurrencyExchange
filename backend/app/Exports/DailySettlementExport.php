<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DailySettlementExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private array $data)
    {
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['channels'] as $row) {
            $rows[] = [
                $row['channel_name'],
                $row['yesterday_balance'],
                $row['today_income_cny'],
                $row['today_expense_cny'],
                $row['current_balance'],
                $row['today_income_hkd'],
                $row['today_expense_hkd'],
                $row['profit'],
            ];
        }
        // 合计行
        $rows[] = ['合计', '', '', '', $this->data['total_balance'], '', '', $this->data['total_profit']];
        return $rows;
    }

    public function headings(): array
    {
        return [
            '渠道', '昨日结余(人民币)', '今日入账(人民币)', '今日出账(人民币)', '当前余额(人民币)', '今日入账(港币)', '今日出账(港币)', '利润(港币)'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 标题行样式
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '366092']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $sheet->getStyle('A2:H' . $sheet->getHighestRow())->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getStyle('B:H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        return [];
    }
}


