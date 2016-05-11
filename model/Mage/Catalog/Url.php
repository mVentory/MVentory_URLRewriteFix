<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/URLRewriteFix
 * @copyright Copyright (c) 2016 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

/**
 * Attribute manager
 *
 * @package MVentory/URLRewriteFix
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */
class MVentory_URLRewriteFix_Model_Mage_Catalog_Url
  extends Mage_Catalog_Model_Url
  {
  /**
   * Get requestPath that was not used yet.
   *
   * Will try to get unique path by adding -1 -2 etc. between url_key
   * and optional url_suffix
   *
   * @param int $storeId
   * @param string $requestPath
   * @param string $idPath
   * @return string
   */
  public function getUnusedPath($storeId, $requestPath, $idPath)
  {
    if (strpos($idPath, 'product') !== false)
      $suffix = $this->getProductUrlSuffix($storeId);
    else
      $suffix = $this->getCategoryUrlSuffix($storeId);

    if (empty($requestPath))
      $requestPath = '-';
    elseif ($requestPath == $suffix)
      $requestPath = '-' . $suffix;

    /**
     * Validate maximum length of request path
     */
    if (strlen($requestPath)
          > self::MAX_REQUEST_PATH_LENGTH + self::ALLOWED_REQUEST_PATH_OVERFLOW)
      $requestPath = substr($requestPath, 0, self::MAX_REQUEST_PATH_LENGTH);

    if (isset($this->_rewrites[$idPath])) {
      $this->_rewrite = $this->_rewrites[$idPath];

      if ($this->_rewrites[$idPath]->getRequestPath() == $requestPath)
        return $requestPath;
    }
    else
      $this->_rewrite = null;

    $rewrite = $this
      ->getResource()
      ->getRewriteByRequestPath($requestPath, $storeId);

    if ($rewrite && $rewrite->getId()) {
      if ($rewrite->getIdPath() == $idPath) {
        $this->_rewrite = $rewrite;
        return $requestPath;
      }

      /**
       * PATCH: Fix the problem with duplicate URLs.
       *
       * Avoid unnecessary creation of new url_keys for duplicate url keys
       *
       * ---------------------------- PATCH BEGINS -----------------------------
       */

      $noSuffixPath = substr($requestPath, 0, -(strlen($suffix)));
      $regEx = '#^('
               . preg_quote($noSuffixPath)
               . ')(-([0-9]+))?('
               . preg_quote($suffix)
               . ')#i';

      $currentRewrite = $this
        ->getResource()
        ->getRewriteByIdPath($idPath, $storeId);

      if ($currentRewrite
            && preg_match($regEx, $currentRewrite->getRequestPath(), $match)) {
        $this->_rewrite = $currentRewrite;
        return $currentRewrite->getRequestPath();
      }

      /**
       * ----------------------------- PATCH ENDS ------------------------------
       */

      // match request_url abcdef1234(-12)(.html) pattern
      $match = [];
      $regularExpression = '#^([0-9a-z/-]+?)(-([0-9]+))?('
                           . preg_quote($suffix)
                           . ')?$#i';

      if (!preg_match($regularExpression, $requestPath, $match))
        return $this->getUnusedPath($storeId, '-', $idPath);

      /**
       * PATCH: Fix the problem with duplicate URLs.
       *
       * Always use full prefix of url_key
       *
       * ---------------------------- PATCH BEGINS -----------------------------
       */

      //$match[1] = $match[1] . '-';
      $match[1] = $noSuffixPath . '-';

      //Don't start counting with a possible number in the url_key
      unset($match[3]);

      /**
       * ----------------------------- PATCH ENDS ------------------------------
       */

      $match[4] = isset($match[4]) ? $match[4] : '';

      $lastRequestPath = $this
        ->getResource()
        ->getLastUsedRewriteRequestIncrement($match[1], $match[4], $storeId);

      if ($lastRequestPath)
        $match[3] = $lastRequestPath;

      return $match[1]
             . (isset($match[3]) ? ($match[3]+1) : '1')
             . $match[4];
    }
    else
      return $requestPath;
  }
}
