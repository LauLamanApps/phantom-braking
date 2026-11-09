<?php

declare(strict_types=1);

namespace App\Statistics\Presentation\Chart;

use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Builds single-series Chart.js charts. Colours are not set here: the
 * `chart-theme` hook in assets/app.js applies the CSS design tokens so
 * light/dark mode is handled in one place.
 */
final readonly class StatisticsChartFactory
{
    public function __construct(
        private ChartBuilderInterface $chartBuilder,
        private TranslatorInterface $translator,
    ) {
    }

    /** @param array<int|string, int> $counts enum value → count */
    public function enumBars(array $counts, string $translationPrefix, string $seriesLabel = 'stats.reports'): Chart
    {
        $labels = array_map(fn (string|int $key): string => $this->translator->trans($translationPrefix.$key), array_keys($counts));

        return $this->bars($labels, array_values($counts), $seriesLabel, horizontal: true);
    }

    /**
     * @param list<string> $labels
     * @param list<int>    $values
     */
    public function bars(array $labels, array $values, string $seriesLabel = 'stats.reports', bool $horizontal = false): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => $this->translator->trans($seriesLabel),
                'data' => $values,
                'borderRadius' => 4,
                'borderSkipped' => 'start',
                'maxBarThickness' => $horizontal ? 18 : 28,
            ]],
        ]);
        $chart->setOptions([
            'indexAxis' => $horizontal ? 'y' : 'x',
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                ($horizontal ? 'x' : 'y') => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ($horizontal ? 'y' : 'x') => ['grid' => ['display' => false]],
            ],
        ]);
        $chart->setAttributes(['data-chart-theme' => 'bar']);

        return $chart;
    }

    /** @param array<string, int> $byMonth */
    public function trend(array $byMonth): Chart
    {
        // Month names in the visitor's language ("okt. 2026", "Okt. 2026", "oct. 2026").
        $months = new \IntlDateFormatter($this->translator->getLocale(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'MMM y');

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => array_map(static fn (string $m): string => (string) $months->format(new \DateTimeImmutable($m.'-01', new \DateTimeZone('UTC'))), array_keys($byMonth)),
            'datasets' => [[
                'label' => $this->translator->trans('stats.reports'),
                'data' => array_values($byMonth),
                'borderWidth' => 2,
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'tension' => 0.25,
                'fill' => true,
            ]],
        ]);
        $chart->setOptions([
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false]],
            ],
        ]);
        $chart->setAttributes(['data-chart-theme' => 'line']);

        return $chart;
    }
}
