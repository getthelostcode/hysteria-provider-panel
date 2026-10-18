<?php

namespace App\Support;

use App\Models\ConfigTemplate;
use App\Models\Provider;
use App\Models\ProviderConfigTemplate;

/**
 * 解析「某运营商实际生效的标准 config.yaml」：
 *   服务商自定义模板 > 平台标准模板。
 */
class ConfigTemplateResolver
{
    /**
     * @return array{content: string, version: int, sha256: string, source: string, source_label: string, provider: ?Provider, updated_at: ?string}
     */
    public static function forProviderCode(?string $code): array
    {
        $provider = $code ? Provider::query()->where('code', $code)->first() : null;

        if ($provider) {
            $own = ProviderConfigTemplate::query()->where('provider_id', $provider->id)->first();

            if ($own !== null && trim($own->content) !== '') {
                return [
                    'content' => $own->content,
                    'version' => $own->version,
                    'sha256' => $own->sha256(),
                    'source' => 'provider',
                    'source_label' => '服务商自定义',
                    'provider' => $provider,
                    'updated_at' => $own->updated_at?->toIso8601String(),
                ];
            }
        }

        $standard = ConfigTemplate::standard();

        return [
            'content' => $standard->content,
            'version' => $standard->version,
            'sha256' => $standard->sha256(),
            'source' => 'standard',
            'source_label' => '平台标准',
            'provider' => $provider,
            'updated_at' => $standard->updated_at?->toIso8601String(),
        ];
    }

    public static function forProvider(Provider $provider): array
    {
        return self::forProviderCode($provider->code);
    }
}
