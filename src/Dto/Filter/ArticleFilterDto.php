<?php

namespace App\Dto\Filter;

use Symfony\Component\Validator\Constraints as Assert;

class ArticleFilterDto
{
    public function __construct(
        #[Assert\Positive]
        private readonly int $page=1,

        #[Assert\Positive]
        private readonly int $limit=6,
    )
    {
    }

        /**
         * Get the value of page
         */
        public function getPage()
        {
                return $this->page;
        }

        /**
         * Get the value of limit
         */
        public function getLimit()
        {
                return $this->limit;
        }
}