<?php

namespace App\Exports;

use App\Models\Channel;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ChannelSummaryExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = Carbon::parse($startDate);
        $this->endDate = Carbon::parse($endDate)->endOfDay();
    }

    public function collection()
    {
        $channels = Channel::with('transactions')->get();

        return $channels->map(function ($channel) {
            $transactions = $channel->transactions()
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->get();

            $incomeTransactions = $transactions->where('type', 'income');
            $outcomeTransactions = $transactions->where('type', 'outcome');
            $exchangeTransactions = $transactions->where('type', 'exchange');

            return [
                'channel_name' => $channel->name,
                'channel_code' => $channel->code,
                'category' => $this->getCategoryLabel($channel->category),
                'status' => $channel->status === 'active' ? '启用' : '停用',
                'total_count' => $transactions->count(),
                'income_count' => $incomeTransactions->count(),
                'outcome_count' => $outcomeTransactions->count(),
                'exchange_count' => $exchangeTransactions->count(),
                'rmb_income_total' => $incomeTransactions->sum('rmb_amount'),
                'rmb_outcome_total' => $outcomeTransactions->sum('rmb_amount'),
                'hkd_income_total' => $incomeTransactions->sum('hkd_amount'),
                'hkd_outcome_total' => $outcomeTransactions->sum('hkd_amount'),
                'current_rmb_balance' => $channel->getRmbBalance(),
                'current_hkd_balance' => $channel->getHkdBalance(),
            ];
        });
    }

    public function headings(): array
    {
        return [
            '支付渠道',
            '渠道代码',
            '分类',
            '状态',
            '总交易笔数',
            '入账笔数',
            '出账笔数',
            '兑换笔数',
            '人民币入账总额',
            '人民币出账总额',
            '港币入账总额',
            '港币出账总额',
            '当前人民币余额',
            '当前港币余额',
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

        // 设置数字列右对齐
        $sheet->getStyle('E:N')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }

    private function getCategoryLabel(string $category): string
    {
        return match($category) {
            'bank' => '银行',
            'ewallet' => '电子钱包',
            'cash' => '现金',
            'other' => '其他',
            default => $category
        };
    }
}
