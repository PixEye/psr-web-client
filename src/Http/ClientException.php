<?php
/** ClientException.php
 * @since		2024.10.28 First commit of this file on 2024-10-28
 * @author		<jmoreau@pixeye.net>
 */

namespace Http;

use Psr\Http\Client\ClientExceptionInterface;

class ClientException extends \Exception implements ClientExceptionInterface {
}
