<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MonthlyDetailSummarySheet implements FromArray, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $summary,
        private int $year,
        private int $month
    ) {
    }

    public function array(): array
    {
        return [
            ['月度收支明细汇总', ''],
            ['', ''],
            ['统计项目', '金额（¥）'],
            ['总收入', number_format($this->summary['total_income'], 2)],
            ['总支出', number_format($this->summary['total_expenses'], 2)],
            ['总利润', number_format($this->summary['total_profit'], 2)],
            ['', ''],
            ['报表期间', sprintf('%d年%d月', $this->year, $this->month)],
            ['生成时间', date('Y-m-d H:i:s')],
        ];
    }

    public function title(): string
    {
        return '汇总统计';
    }

    public function styles(Worksheet $sheet)
    {
        // 标题行样式（第一行）
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // 表头样式（第三行）
        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '366092']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // 数据行样式（第4-6行）
        $sheet->getStyle('A4:B6')->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // 总收入行 - 绿色背景
        $sheet->getStyle('A4:B4')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E8F5E9']],
            'font' => ['bold' => true],
        ]);

        // 总支出行 - 红色背景
        $sheet->getStyle('A5:B5')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEBEE']],
            'font' => ['bold' => true],
        ]);

        // 总利润行 - 蓝色背景
        $sheet->getStyle('A6:B6')->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'E3F2FD']],
            'font' => ['bold' => true, 'size' => 12],
        ]);

        // 金额列右对齐
        $sheet->getStyle('B4:B6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // 报表信息样式（第8-9行）
        $sheet->getStyle('A8:B9')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => '666666']],
        ]);

        return [];
    }
}
