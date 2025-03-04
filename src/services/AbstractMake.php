<?php declare(strict_types=1);
/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012, 2022 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */

namespace Elabftw\Services;

use function dirname;
use Elabftw\Elabftw\Db;
use Elabftw\Elabftw\FsTools;
use Elabftw\Models\AbstractEntity;
use Elabftw\Traits\UploadTrait;
use const SITE_URL;

/**
 * Mother class of the Make* services
 */
abstract class AbstractMake
{
    use UploadTrait;

    public string $filePath = '';

    // a place to gather errors or warnings generated during the making
    public array $errors = array();

    protected int $contentSize = 0;

    protected Db $Db;

    protected string $contentType = 'application/octet-stream';

    public function __construct(protected AbstractEntity $Entity)
    {
        $this->Db = Db::getConnection();
    }

    /**
     * The filename for what we are making
     */
    abstract public function getFileName(): string;

    public function getContentSize(): int
    {
        return $this->contentSize;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Get the contents of assets/pdf.min.css
     */
    protected function getCss(): string
    {
        $assetsFs = FsTools::getFs(dirname(__DIR__, 2) . '/web/assets');
        return $assetsFs->read('pdf.min.css');
    }

    /**
     * Return the url of the item or experiment
     *
     * @return string url to the item/experiment
     */
    protected function getUrl(?int $entityId = null): string
    {
        return sprintf(
            '%s/%s.php?mode=view&id=%d',
            SITE_URL,
            $this->Entity->page,
            $entityId ?? $this->Entity->id ?? 0,
        );
    }
}
