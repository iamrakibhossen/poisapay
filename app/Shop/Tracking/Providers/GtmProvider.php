<?php

declare(strict_types=1);

namespace App\Shop\Tracking\Providers;

/**
 * Google Tag Manager. Loads the container (head) + <noscript> iframe (body per
 * Google's spec) and pushes every internal event onto the dataLayer as
 * `{ event: <type>, ...payload }`, so tags are configured inside GTM itself.
 * https://developers.google.com/tag-platform/tag-manager/web
 */
final class GtmProvider extends AbstractTrackingProvider
{
    public function key(): string
    {
        return 'gtm';
    }

    public function label(): string
    {
        return 'Google Tag Manager';
    }

    public function docsUrl(): string
    {
        return 'https://developers.google.com/tag-platform/tag-manager/web';
    }

    public function fields(): array
    {
        return [
            $this->field('container_id', 'Container ID', true, '^GTM-[A-Z0-9]{4,10}$', 'Starts with GTM- (e.g. GTM-XXXXXX).'),
        ];
    }

    public function headScript(array $config): string
    {
        if (! $this->isConfigured($config)) {
            return '';
        }

        $id = $this->js($this->primaryId($config));

        return <<<HTML
        <!-- Google Tag Manager -->
        <script>
        (window.__ppTrackers=window.__ppTrackers||[]).push({key:'gtm',init:function(){(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=!0;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f)})(window,document,'script','dataLayer',{$id});},fire:function(type,data){window.dataLayer=window.dataLayer||[];window.dataLayer.push(Object.assign({event:type},data||{}));}});
        </script>
        <!-- End Google Tag Manager -->
        HTML;
    }

    public function bodyHtml(array $config): string
    {
        if (! $this->isConfigured($config)) {
            return '';
        }

        $id = rawurlencode($this->primaryId($config));

        return '<!-- Google Tag Manager (noscript) -->'
            .'<noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$id.'"'
            .' height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>'
            .'<!-- End Google Tag Manager (noscript) -->';
    }
}
