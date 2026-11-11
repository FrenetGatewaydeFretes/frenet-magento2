<?php
/**
 * Frenet Shipping Gateway
 *
 * @category Frenet
 */

declare(strict_types=1);

namespace Frenet\Shipping\ViewModel\Labels;

use Frenet\Shipping\Model\Labels\CarrierLogo;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Carrier logos on the labels screens: the bundled official files, or a monogram in the brand colour.
 */
class Carriers implements ArgumentInterface
{
    /**
     * @param CarrierLogo $logos
     * @param AssetRepository $assets
     * @param Escaper $escaper
     * @param Json $json
     */
    public function __construct(
        private readonly CarrierLogo $logos,
        private readonly AssetRepository $assets,
        private readonly Escaper $escaper,
        private readonly Json $json
    ) {
    }

    /**
     * Logo of the carrier as escaped HTML (its name in the alt text), or its monogram with the name.
     *
     * @param string $code Frenet carrier code.
     * @param string $name Carrier name.
     * @return string
     */
    public function html(string $code, string $name = ''): string
    {
        $known = $this->logos->code($code, $name);
        $label = trim($name) !== '' ? trim($name) : $known;
        $file = $this->logos->file($known);
        if ($file !== null) {
            return '<span class="frenet-carrier"><img class="frenet-carrier__logo" src="'
                . $this->escaper->escapeUrl($this->assets->getUrl($file)) . '" alt="'
                . $this->escaper->escapeHtmlAttr($label) . '" height="20" loading="lazy" decoding="async"/></span>';
        }
        return '<span class="frenet-carrier"><span class="frenet-carrier__mark" style="background:'
            . $this->escaper->escapeHtmlAttr($this->logos->color($known)) . '" aria-hidden="true">'
            . $this->escaper->escapeHtml($this->logos->initials($label, $known))
            . '</span><span class="frenet-carrier__name">' . $this->escaper->escapeHtml($label) . '</span></span>';
    }

    /**
     * Logo of the carrier followed by the service name, as escaped HTML; a dash when there is neither.
     *
     * @param string $code Frenet carrier code.
     * @param string $name Carrier name.
     * @param string $service Service name or code.
     * @return string
     */
    public function serviceHtml(string $code, string $name, string $service): string
    {
        if (trim($code) === '' && trim($service) === '') {
            return '<span class="frenet-muted">—</span>';
        }
        return '<span class="frenet-svc">' . (trim($code) !== '' ? $this->html($code, $name) : '')
            . '<span>' . $this->escaper->escapeHtml($service) . '</span></span>';
    }

    /**
     * What the page script needs to draw the same logos: URLs by code, colours and name needles, as JSON.
     *
     * @return string
     */
    public function data(): string
    {
        $logos = [];
        foreach ($this->logos->files() as $code => $file) {
            $logos[$code] = $this->assets->getUrl($file);
        }
        return (string) $this->json->serialize([
            'logos' => $logos,
            'colors' => $this->logos->colors(),
            'names' => $this->logos->names(),
            'palette' => $this->logos->palette(),
        ]);
    }
}
