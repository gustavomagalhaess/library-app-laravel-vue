<?php

declare(strict_types=1);

namespace App\Domain\Author\Exceptions;

use App\Domain\Author\Models\Author;
use RuntimeException;

class AuthorHasBooksException extends RuntimeException
{
    public static function for(Author $author): self
    {
        return new self(sprintf(
            'Author "%s" cannot be deleted because they are associated with existing books.'
            .' Remove or reassign the related books first.',
            $author->name,
        ));
    }
}
