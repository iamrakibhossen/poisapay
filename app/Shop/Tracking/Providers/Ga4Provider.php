<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Providers;

/**
 * Google Analytics 4. Recommended events: page_view, view_item, add_to_cart,
 * begin_checkout, purchase, generate_lead — anything else is sent as a custom
 * event keyed by the internal type. GA4 anonymizes IPs by default.
 * https://developers.google.com/analytics/devguides/collection/ga4/reference/events
 */
final class Ga4Provider extends AbstractTrackingProvider
{
    public function key(): string
    {
        return 'ga4';
    }

    public function label(): string
    {
        return 'Google Analytics 4';
    }

    public function docsUrl(): string
    {
        return 'https://support.google.com/analytics/answer/9304153';
    }

    public function fields(): array
    {
        return [
            $this->field('measurement_id', 'Measurement ID', true, '^G-[A-Z0-9]{4,12}$', 'Starts with G- (e.g. G-XXXXXXXX).'),
        ];
    }

    public function headScript(array $config): string
    {
        if (! $this->isConfigured($config)) {
            return '';
        }

        $id = $this->js($this->primaryId($config));
        $idRaw = rawurlencode($this->primaryId($config));

        return <<<HTML
        <!-- Google Analytics 4 -->
        <script>
        (window.__ppTrackers=window.__ppTrackers||[]).push({key:'ga4',init:function(){var s=document.createElement('script');s.async=!0;s.src='https://www.googletagmanager.com/gtag/js?id={$idRaw}';document.head.appendChild(s);window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments)};gtag('js',new Date());gtag('config',{$id});},fire:function(type,data){if(typeof gtag!=='function')return;var m={page_view:'page_view',view_content:'view_item',add_to_cart:'add_to_cart',initiate_checkout:'begin_checkout',purchase:'purchase',lead:'generate_lead'},p={};if(data.currency)p.currency=data.currency;if(data.value!=null)p.value=Number(data.value);if(data.order_id!=null)p.transaction_id=String(data.order_id);if(data.product_id!=null){p.items=[{item_id:String(data.product_id),item_name:data.product_name||undefined,quantity:data.quantity!=null?Number(data.quantity):undefined}];}gtag('event',m[type]||type,p);}});
        </script>
        <!-- End Google Analytics 4 -->
        HTML;
    }
}
