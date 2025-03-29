<?php

/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2024 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

declare(strict_types=1);

namespace Elabftw\Import;

use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Compounds;
use Elabftw\Models\Compounds2ItemsLinks;
use Elabftw\Models\Items;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Console\Output\OutputInterface;
use Override;

/**
 * Import a CSV into compounds
 */
final class CompoundsCsv extends AbstractCsv
{
    public function __construct(
        private OutputInterface $output,
        protected Items $Items,
        protected UploadedFile $UploadedFile,
        protected Compounds $Compounds,
        protected ?int $resourceCategory = null,
    ) {
        parent::__construct($Items->Users, $UploadedFile);
    }

    #[Override]
    public function import(): int
    {
        // number of rows is stored in a var so we can decrement it when a compound is not imported
        $count = $this->getCount();

        foreach ($this->reader->getRecords() as $row) {
            try {
                $id = $this->Compounds->create(
                    casNumber: $row['cas'] ?? null,
                    ecNumber: $row['ec_number'] ?? null,
                    inchi: $row['inchi'] ?? null,
                    inchiKey: $row['inchikey'] ?? null,
                    iupacName: $row['iupacname'] ?? null,
                    name: $row['name'] ?? null,
                    chebiId: $row['chebi_id'] ?? null,
                    chemblId: $row['chembl_id'] ?? null,
                    deaNumber: $row['dea_number'] ?? null,
                    drugbankId: $row['drugbank_id'] ?? null,
                    dsstoxId: $row['dsstox_id'] ?? null,
                    hmdbId: $row['hmdb_id'] ?? null,
                    keggId: $row['kegg_id'] ?? null,
                    metabolomicsWbId: $row['metabolomics_wb_id'] ?? null,
                    molecularFormula: $row['molecularformula'] ?? null,
                    molecularWeight: isset($row['molecularweight']) ? (float) $row['molecularweight'] : 0.00,
                    nciCode: $row['nci_code'] ?? null,
                    nikkajiNumber: $row['nikkaji_number'] ?? null,
                    pharmGkbId: $row['pharmgkb_id'] ?? null,
                    pharosLigandId: $row['pharos_ligand_id'] ?? null,
                    pubchemCid: empty($row['pubchemcid']) ? null : (int) $row['pubchemcid'],
                    rxcui: $row['rxcui'] ?? null,
                    smiles: $row['smiles'] ?? null,
                    unii: $row['unii'] ?? null,
                    wikidata: $row['wikidata'] ?? null,
                    wikipedia: $row['wikipedia'] ?? null,
                    isCorrosive: (bool) ($row['is_corrosive'] ?? false),
                    isExplosive: (bool) ($row['is_explosive'] ?? false),
                    isFlammable: (bool) ($row['is_flammable'] ?? false),
                    isGasUnderPressure: (bool) ($row['is_gas_under_pressure'] ?? false),
                    isHazardous2env: (bool) ($row['is_hazardous2env'] ?? false),
                    isHazardous2health: (bool) ($row['is_hazardous2health'] ?? false),
                    isOxidising: (bool) ($row['is_oxidising'] ?? false),
                    isSeriousHealthHazard: (bool) ($row['is_serious_health_hazard'] ?? false),
                    isToxic: (bool) ($row['is_toxic'] ?? false),
                    isRadioactive: (bool) ($row['is_radioactive'] ?? false),
                    isAntibioticPrecursor: (bool) ($row['is_antibiotic_precursor'] ?? false),
                    isDrugPrecursor: (bool) ($row['is_drug_precursor'] ?? false),
                    isExplosivePrecursor: (bool) ($row['is_explosive_precursor'] ?? false),
                    isCmr: (bool) ($row['is_cmr'] ?? false),
                    isNano: (bool) ($row['is_nano'] ?? false),
                    isControlled: (bool) ($row['is_controlled'] ?? false)
                );
                if ($this->resourceCategory !== null) {
                    $resource = $this->Items->create(template: $this->resourceCategory, title: $row['name'] ?? $row['iupacname'] ?? 'Unnamed compound');
                    $this->Items->setId($resource);
                    $Compounds2ItemsLinks = new Compounds2ItemsLinks($this->Items, $id);
                    $Compounds2ItemsLinks->create();
                }
            } catch (ImproperActionException $e) {
                $this->output->writeln($e->getMessage());
                // decrement the count so we can give a correct number
                --$count;
            }
        }
        return $count;
    }
}
