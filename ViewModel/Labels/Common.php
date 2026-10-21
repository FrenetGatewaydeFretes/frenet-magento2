<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\LabelsConfig;
use Frenet\Shipping\Model\Labels\StatusName;
use Frenet\Shipping\Model\Labels\Wallet;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Shared bits of the Frenet labels screens: availability, wallet, money, status pills, URLs.
 */
class Common implements ArgumentInterface
{
    /**
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param UrlInterface $url
     * @param TimezoneInterface $timezone
     * @param Escaper $escaper
     * @param StatusName $statusNames
     */
    public function __construct(
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        private readonly UrlInterface $url,
        private readonly TimezoneInterface $timezone,
        private readonly Escaper $escaper,
        private readonly StatusName $statusNames
    ) {
    }

    /**
     * "Notes" cell, as escaped HTML: why the last label of the order failed and when, or a dash.
     *
     * @param array{message:string,at:string}|null $issue
     * @return string
     */
    public function issueHtml(?array $issue): string
    {
        if (!$issue || (string) ($issue['message'] ?? '') === '') {
            return '<span class="frenet-muted">—</span>';
        }
        $when = $this->when((string) ($issue['at'] ?? ''));
        $whenHtml = $when !== ''
            ? '<span class="frenet-muted frenet-block">' . $this->escaper->escapeHtml($when) . '</span>'
            : '';
        return '<span class="frenet-bad frenet-block">' . $this->escaper->escapeHtml($issue['message']) . '</span>'
            . $whenHtml;
    }

    /**
     * When a "Notes" issue happened (stored in UTC), in the admin's date and time.
     *
     * @param string $utc
     * @return string
     */
    public function when(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        try {
            return $this->timezone->formatDateTime($utc, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);
        } catch (\Throwable $e) {
            return $utc;
        }
    }

    /**
     * Labels enabled and both tokens present.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->config->isAvailable();
    }

    /**
     * Wallet (cached 5 min), or null.
     *
     * @return array{balance: float, labels: int}|null
     */
    public function wallet(): ?array
    {
        return $this->wallet->get();
    }

    /**
     * Wallet read from Frenet now (the "Create shipment" screen shows the balance before buying).
     *
     * @return array{balance: float, labels: int}|null
     */
    public function freshWallet(): ?array
    {
        return $this->wallet->refresh();
    }

    /**
     * Labels settings.
     *
     * @return LabelsConfig
     */
    public function config(): LabelsConfig
    {
        return $this->config;
    }

    /**
     * Brazilian money.
     *
     * @param float $value
     * @return string
     */
    public function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Status label.
     *
     * @param int $status
     * @return string
     */
    public function statusLabel(int $status): string
    {
        return $this->statusNames->label($status);
    }

    /**
     * Status tone (ok, wait, bad, off).
     *
     * @param int $status
     * @return string
     */
    public function statusTone(int $status): string
    {
        return $this->statusNames->tone($status);
    }

    /**
     * Admin URL.
     *
     * @param string $route
     * @param mixed[] $params
     * @return string
     */
    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }

    /**
     * Settings URL (carriers section).
     *
     * @return string
     */
    public function settingsUrl(): string
    {
        return $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'carriers'])
            . '#carriers_frenetshipping-link';
    }

    /**
     * Frenet panel (add balance, journey "panel").
     *
     * @return string
     */
    public function panelUrl(): string
    {
        return LabelsConfig::PANEL_URL;
    }
}
