<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 一覧画面の GET 検索パラメータを受け取り、コレクションを絞り込む。
 */
class ListSearch
{
  public const PARAMS = ['code', 'customer', 'supplier', 'sku', 'due_from', 'due_to', 'status'];

  /** @return array<string, string> */
  public static function params(Request $request): array
  {
    $params = [];
    foreach (self::PARAMS as $key) {
      $params[$key] = trim((string) $request->query($key, ''));
    }

    return $params;
  }

  /** @param array<string, string> $params */
  public static function isActive(array $params): bool
  {
    return collect($params)->contains(fn ($value) => $value !== '');
  }

  /**
   * @param  array<string, string>  $params
   * @param  array<string, mixed>  $options
   */
  public static function filter(Collection $items, array $params, array $options = []): Collection
  {
    $options = array_merge([
      'code_fields' => ['code'],
      'customer_field' => 'customer',
      'supplier_field' => 'supplier',
      'sku_fields' => ['sku', 'product'],
      'date_field' => 'due_date',
      'status_field' => 'status',
      'status_resolver' => null,
    ], $options);

    return $items->filter(function ($item) use ($params, $options) {
      if ($params['code'] !== '') {
        $matched = false;
        foreach ($options['code_fields'] as $field) {
          $value = data_get($item, $field);
          if ($value !== null && self::contains($value, $params['code'])) {
            $matched = true;
            break;
          }
        }
        if (! $matched) {
          return false;
        }
      }

      if ($params['customer'] !== '') {
        $value = data_get($item, $options['customer_field']);
        if ($value === null || ! self::contains($value, $params['customer'])) {
          return false;
        }
      }

      if ($params['supplier'] !== '') {
        $value = data_get($item, $options['supplier_field']);
        if ($value === null || ! self::contains($value, $params['supplier'])) {
          return false;
        }
      }

      if ($params['sku'] !== '') {
        $matched = false;
        foreach ($options['sku_fields'] as $field) {
          $value = data_get($item, $field);
          if ($value !== null && self::contains($value, $params['sku'])) {
            $matched = true;
            break;
          }
        }
        if (! $matched) {
          return false;
        }
      }

      if ($params['due_from'] !== '' || $params['due_to'] !== '') {
        $date = data_get($item, $options['date_field']);
        if ($date === null) {
          return false;
        }
        if ($params['due_from'] !== '' && $date < $params['due_from']) {
          return false;
        }
        if ($params['due_to'] !== '' && $date > $params['due_to']) {
          return false;
        }
      }

      if ($params['status'] !== '') {
        if (is_callable($options['status_resolver'])) {
          if (! ($options['status_resolver'])($item, $params['status'])) {
            return false;
          }
        } else {
          $value = data_get($item, $options['status_field']);
          if ($value !== $params['status']) {
            return false;
          }
        }
      }

      return true;
    })->values();
  }

  private static function contains(string $haystack, string $needle): bool
  {
    return str_contains(mb_strtolower($haystack), mb_strtolower($needle));
  }
}
