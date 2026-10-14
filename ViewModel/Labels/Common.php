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
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Shared bits of the Frenet labels screens: availability, simulator banner, wallet, money, status pills, URLs.
 */
class Common implements ArgumentInterface
{
    /**
     * @param LabelsConfig $config
     * @param Wallet $wallet
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly LabelsConfig $config,
        private readonly Wallet $wallet,
        private readonly UrlInterface $url
    ) {
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
     * Whether the API URL points to the local simulator.
     *
     * @return bool
     */
    public function isSimulated(): bool
    {
        return $this->config->isSimulated();
    }

    /**
     * Wallet (cached 5 min), or null.
     *
     * @param bool $fresh
     * @return array{balance: float, labels: int}|null
     */
    public function wallet(bool $fresh = false): ?array
    {
        return $this->wallet->get($fresh);
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
        return StatusName::label($status);
    }

    /**
     * Status tone (ok, wait, bad, off).
     *
     * @param int $status
     * @return string
     */
    public function statusTone(int $status): string
    {
        return StatusName::tone($status);
    }

    /**
     * Admin URL.
     *
     * @param string $route
     * @param array $params
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
        return $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'carriers']) . '#carriers_frenetshipping-link';
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
