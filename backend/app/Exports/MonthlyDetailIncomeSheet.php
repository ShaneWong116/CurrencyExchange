<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyDetailIncomeSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $incomeData,
        private int $year,
        private int $month
    ) {
    }

    public function array(): array
    {
        $rows = [];
        $index = 1;
        
        foreach ($this->incomeData as $income) {
            $rows[] = [
                $index++,
                $income['date_display'],
                $income['total_profit'] > 0 ? number_format($income['total_profit'], 2) : '-',
                $income['items'],
                $income['remarks'] ?: '-',
            ];
        }
        
        return $rows;
    }

    public function headings(): array
    {
        return ['序号', '日期', '金额', '项目', '备注'];
    }

    public function title(): string
    {
        return '收入明细';
    }

    public function styles(Worksheet $sheet)
    {
        // 标题行样式
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '16a34a']], // 绿色
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // 数据行样式
        $lastRow = count($this->incomeData) + 1;
        $sheet->getStyle('A2:E' . $lastRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // 序号列居中
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // 金额列右对齐
        $sheet->getStyle('C:C')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        return [];
    }
}
