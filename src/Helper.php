<?php

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE file.
 * Redistributions of files must retain the above copyright notice.
 * 
 * @copyright (c) 2014, Achmad F. Ibrahim
 * @link https://github.com/acfatah
 * @license http://opensource.org/licenses/mit-license.php The MIT License (MIT)
 */

namespace Console;

class Helper
{
    public static function findNamespace($file)
    {
        if (!file_exists($file)) {
            throw new \RuntimeException(sprintf('Cannot find file: %s', $file));
        }
        
        $res = new \SplFileObject($file);
        
        while(!$res->eof()) {
        
            if (strpos($res->current(), 'namespace ') !== false) {
                $namespace = trim(str_replace('namespace ', '', $res->current()));
                $namespace = rtrim($namespace, ';');
                return $namespace;
            }
            
            $res->next();
        }
    }
}
