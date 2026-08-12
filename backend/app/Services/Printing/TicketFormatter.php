<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AutoTranslator;
use Illuminate\Support\Carbon;

class TicketFormatter
{
    private const WIDTH = 42;

    /**
     * English source labels — other locales are translated dynamically.
     *
     * @var array<string, string>
     */
    private const SOURCE_LABELS = [
        'kitchen' => 'KITCHEN',
        'bar' => 'BAR',
        'order' => 'Order',
        'table' => 'Table',
        'umbrella' => 'Umbrella',
        'sunbed' => 'Sunbed',
        'time' => 'Time',
        'order_note' => 'ORDER NOTE',
        'item_note' => 'Note',
        'variant' => 'Variant',
        'addon' => 'Extra',
        'reprint' => '*** REPRINT ***',
        'test' => '*** TEST PRINT ***',
    ];

    /**
     * Build human-readable preview (plain text).
     *
     * @param  iterable<OrderItem>  $items
     */
    public function formatText(
        Order $order,
        string $station,
        iterable $items,
        string $locale = 'it',
        bool $reprint = false,
        bool $test = false,
    ): string {
        $labels = $this->labelsFor($locale);
        $lines = [];

        if ($test) {
            $lines[] = $this->center($labels['test']);
        }
        if ($reprint) {
            $lines[] = $this->center($labels['reprint']);
        }

        $lines[] = $this->center($labels[$station] ?? strtoupper($station));
        $lines[] = str_repeat('=', self::WIDTH);
        $lines[] = $labels['order'].': '.$order->order_number;
        $lines[] = $this->locationLine($order, $labels);
        $lines[] = $labels['time'].': '.$this->formatTime($order);
        $lines[] = str_repeat('-', self::WIDTH);

        foreach ($items as $item) {
            $lines = array_merge($lines, $this->formatItemLines($item, $labels));
        }

        if ($order->notes) {
            $lines[] = str_repeat('-', self::WIDTH);
            $lines[] = $labels['order_note'].':';
            $lines[] = $this->wrap($order->notes);
        }

        $lines[] = str_repeat('=', self::WIDTH);

        return implode("\n", $lines)."\n";
    }

    /**
     * Build ESC/POS binary payload from plain text ticket.
     */
    public function formatEscPos(string $text): string
    {
        $init = "\x1B\x40"; // ESC @
        $center = "\x1B\x61\x01"; // ESC a 1
        $left = "\x1B\x61\x00"; // ESC a 0
        $boldOn = "\x1B\x45\x01";
        $boldOff = "\x1B\x45\x00";
        $doubleOn = "\x1D\x21\x11"; // GS ! double
        $doubleOff = "\x1D\x21\x00";
        $cut = "\x1D\x56\x00"; // GS V full cut
        $lf = "\n";

        $output = $init;

        foreach (explode("\n", rtrim($text)) as $line) {
            if ($line === str_repeat('=', self::WIDTH) || $line === str_repeat('-', self::WIDTH)) {
                $output .= $left.$this->encode($line).$lf;
                continue;
            }

            if (str_contains($line, '***')) {
                $output .= $center.$boldOn.$this->encode($line).$boldOff.$lf;
                continue;
            }

            $trimmed = trim($line);
            // Station header (en source + common MT/legacy variants).
            if (preg_match('/^(CUCINA|BAR|KITCHEN|KÜCHE|ΚΟΥΖΙΝΑ|ΜΠΑΡ)$/ui', $trimmed)) {
                $output .= $center.$doubleOn.$boldOn.$this->encode($line).$boldOff.$doubleOff.$lf;
                continue;
            }

            if (preg_match('/^(Ordine|Order|Bestellung|Παραγγελία):/ui', $trimmed)) {
                $output .= $left.$boldOn.$this->encode($line).$boldOff.$lf;
                continue;
            }

            if (preg_match('/^(NOTA ORDINE|ORDER NOTE|BESTELLNOTIZ|ΣΗΜΕΙΩΣΗ ΠΑΡΑΓΓΕΛΙΑΣ):/ui', $trimmed)) {
                $output .= $left.$boldOn.$this->encode($line).$boldOff.$lf;
                continue;
            }

            if (preg_match('/^\d+x/u', $line)) {
                $output .= $left.$boldOn.$this->encode($line).$boldOff.$lf;
                continue;
            }

            $output .= $left.$this->encode($line).$lf;
        }

        $output .= $lf.$lf.$cut;

        return $output;
    }

    /**
     * @return array<string, string>
     */
    private function labelsFor(string $locale): array
    {
        $locale = strtolower(substr($locale, 0, 2));
        if ($locale === '' || $locale === 'en') {
            return self::SOURCE_LABELS;
        }

        $translator = app(AutoTranslator::class);
        $labels = [];
        foreach (self::SOURCE_LABELS as $key => $english) {
            $labels[$key] = $translator->translate($english, 'en', $locale) ?? $english;
        }

        return $labels;
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<string>
     */
    private function formatItemLines(OrderItem $item, array $labels): array
    {
        $lines = [];
        $lines[] = $item->quantity.'x '.$item->product_name;

        foreach ($item->variants ?? [] as $variant) {
            $group = $variant['group_name'] ?? $labels['variant'];
            $option = $variant['option_name'] ?? '';
            $lines[] = '   - '.$group.': '.$option;
        }

        foreach ($item->addons ?? [] as $addon) {
            $qty = (int) ($addon['quantity'] ?? 1);
            $name = $addon['name'] ?? $labels['addon'];
            $lines[] = '   + '.$name.($qty > 1 ? ' x'.$qty : '');
        }

        if ($item->notes) {
            $lines[] = '   '.$labels['item_note'].': '.$item->notes;
        }

        return $lines;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function locationLine(Order $order, array $labels): string
    {
        $location = $order->location;
        if (! $location) {
            return $labels['table'].': —';
        }

        $typeLabel = match ($location->type) {
            'umbrella' => $labels['umbrella'],
            'sunbed' => $labels['sunbed'],
            default => $labels['table'],
        };

        $zone = $location->zone ? ' ('.$location->zone.')' : '';

        return $typeLabel.': '.$location->name.$zone;
    }

    private function formatTime(Order $order): string
    {
        $tz = $order->tenant?->timezone ?? 'Europe/Rome';

        return Carbon::parse($order->created_at)->timezone($tz)->format('H:i');
    }

    private function center(string $text): string
    {
        $len = mb_strlen($text);
        if ($len >= self::WIDTH) {
            return $text;
        }

        $pad = (int) floor((self::WIDTH - $len) / 2);

        return str_repeat(' ', $pad).$text;
    }

    private function wrap(string $text): string
    {
        return wordwrap($text, self::WIDTH, "\n", true);
    }

    private function encode(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

        return $converted !== false ? $converted : $text;
    }
}
