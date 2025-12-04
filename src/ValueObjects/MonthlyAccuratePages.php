<?php

namespace Lkt\Factory\Instantiator\ValueObjects;

class MonthlyAccuratePages
{
    /** @var int[] */
    public array $pages = [];
    public int $total = 0;

    public function __construct(array $pages)
    {
        $this->pages = $pages;
        $this->total = count($pages);
    }

    public function getPageIndex(int $page): int
    {
        return array_search($page, $this->pages, true);
    }

    public function getPageNumber(int $page): int
    {
        return $this->getPageIndex($page) + 1;
    }

    public function getPageYearMonth(int $page): ?int
    {
        return $this->pages[$page - 1];
    }

    public function getNextPageByYearMonth(int $month): ?int
    {
        $currentPage = $this->getPageNumber($month);
        return $currentPage + 1;
    }

    public function getNextMonthByYearMonth(int $month): ?int
    {
        $currentPage = $this->getPageNumber($month);
        return $this->pages[$currentPage + 1];
    }
}