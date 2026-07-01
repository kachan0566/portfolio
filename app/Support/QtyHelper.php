<?php

namespace App\Support;

/**
 * メートル数量を「反数（○m）」形式で表示するためのヘルパー。
 */
class QtyHelper
{
  /** 製品品番の標準：1反あたりのメートル数 */
  public const METERS_PER_TAN_PRODUCT = 50;

  /** 生機品番の標準：1反あたりのメートル数 */
  public const METERS_PER_TAN_GREIGE = 100;

  public static function metersPerTan(?int $productId = null, bool $isGreige = false): int
  {
    if ($isGreige) {
      return self::METERS_PER_TAN_GREIGE;
    }

    if ($productId !== null) {
      $product = DemoData::findProduct($productId);
      if ($product !== null && isset($product->meters_per_tan)) {
        return (int) $product->meters_per_tan;
      }
    }

    return self::METERS_PER_TAN_PRODUCT;
  }

  public static function tanCount(float|int $meters, ?int $productId = null, bool $isGreige = false): float
  {
    $perTan = self::metersPerTan($productId, $isGreige);

    return $perTan > 0 ? $meters / $perTan : 0;
  }

  public static function formatTanCount(float|int $tan): string
  {
    if (fmod((float) $tan, 1.0) === 0.0) {
      return number_format($tan, 0);
    }

    return rtrim(rtrim(number_format($tan, 1), '0'), '.');
  }

  public static function format(float|int $meters, ?int $productId = null, bool $isGreige = false): string
  {
    $tan = self::tanCount($meters, $productId, $isGreige);

    return self::formatTanCount($tan) . '反（' . number_format($meters) . 'm）';
  }
}
