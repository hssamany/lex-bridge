<?php

/**
 * Class to represent an Invoice
 */
final class Invoice
{
    public readonly bool $archived;
    public readonly string $voucherDate;
    public readonly array $address;
    public readonly array $lineItems;
    public readonly array $totalPrice;
    public readonly array $taxConditions;
    public readonly array $paymentConditions;
    public readonly array $shippingConditions;
    public readonly string $title;
    public readonly string $introduction;
    public readonly string $remark;
    
    public function __construct(array $data)
    {
        $this->archived = $data['archived'] ?? false;
        $this->voucherDate = $data['voucherDate'] ?? '';
        $this->address = $data['address'] ?? [];
        $this->lineItems = $data['lineItems'] ?? [];
        $this->totalPrice = $data['totalPrice'] ?? [];
        $this->taxConditions = $data['taxConditions'] ?? [];
        $this->paymentConditions = $data['paymentConditions'] ?? [];
        $this->shippingConditions = $data['shippingConditions'] ?? [];
        $this->title = $data['title'] ?? '';
        $this->introduction = $data['introduction'] ?? '';
        $this->remark = $data['remark'] ?? '';
    }
    
    public function toArray(): array
    {
        return [
            'archived' => $this->archived,
            'voucherDate' => $this->voucherDate,
            'address' => $this->address,
            'lineItems' => $this->lineItems,
            'totalPrice' => $this->totalPrice,
            'taxConditions' => $this->taxConditions,
            'paymentConditions' => $this->paymentConditions,
            'shippingConditions' => $this->shippingConditions,
            'title' => $this->title,
            'introduction' => $this->introduction,
            'remark' => $this->remark
        ];
    }
    
    public static function create(
        bool $archived,
        string $voucherDate,
        array $address,
        array $lineItems,
        array $totalPrice,
        array $taxConditions,
        array $paymentConditions,
        array $shippingConditions,
        string $title,
        string $introduction,
        string $remark
    ): self {

        return new self([
            'archived' => $archived,
            'voucherDate' => $voucherDate,
            'address' => $address,
            'lineItems' => $lineItems,
            'totalPrice' => $totalPrice,
            'taxConditions' => $taxConditions,
            'paymentConditions' => $paymentConditions,
            'shippingConditions' => $shippingConditions,
            'title' => $title,
            'introduction' => $introduction,
            'remark' => $remark
        ]);
    }
}
