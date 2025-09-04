<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CurrencyPair;
use App\Repository\Interface\ExchangeRateRepositoryInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExchangeRateRepositoryInterface::class)]
#[ORM\Table(name: 'exchange_rates')]
#[ORM\Index(columns: ['pair', 'created_at'], name: 'idx_pair_created')]
#[ORM\Index(columns: ['pair', 'created_at'], name: 'idx_pair_created_desc', options: ['lengths' => [null, null]])]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10, enumType: CurrencyPair::class)]
    private CurrencyPair $pair;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
    private string $rate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPair(): CurrencyPair
    {
        return $this->pair;
    }

    public function setPair(CurrencyPair $pair): self
    {
        $this->pair = $pair;
        return $this;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): self
    {
        $this->rate = $rate;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
