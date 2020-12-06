<?php

/**
 * @project:   conrete5 Express Batch Importer
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0.0
 */

namespace Bitter\Concrete\Express;

use Concrete\Core\Support\Facade\Application;
use \parseCSV;

// No associations supported

class BatchImporter
{

    /**
     *
     * @param string $handle
     * @param string $fileName
     * @param string $delimiter
     * @param int $batchSize
     */
    public static function batchImportCSV($handle, $fileName, $delimiter = ",", $batchSize = 25)
    {
        $csv = new parseCSV();
        $csv->delimiter = $delimiter;
        $csv->parse($fileName);

        return self::batchImport($handle, $csv->data, $batchSize);
    }

    /**
     *
     * @param string $handle
     * @param array $entries
     * @param int $batchSize
     */
    public static function batchImport($handle, $entries, $batchSize = 25)
    {
        $app = Application::getFacadeApplication();

        $db = $app->make('database');

        $searchIndexTable = str_replace("_", "", ucwords($handle, "_")) . "ExpressSearchIndexAttributes";

        $insertQueries = [];

        $insertQueries["AttributeValues"] = [];
        $insertQueries["ExpressEntityEntries"] = [];
        $insertQueries["ExpressEntityEntryAttributeValues"] = [];
        $insertQueries[$searchIndexTable] = [];

        $entityId = $db->fetchColumn("SELECT id FROM ExpressEntities WHERE handle = ?;", array($handle));

        $entryId = (int)$db->fetchColumn("SELECT exEntryId FROM ExpressEntityEntries ORDER BY exEntryId DESC LIMIT 1;");

        $attributeValueId = (int)$db->fetchColumn("SELECT avID FROM AttributeValues ORDER BY avID DESC LIMIT 1;");

        $attributeKeyIds = [];
        $attributeTables = [];
        $attributeTableFields = [];

        $associationEntryId = (int)$db->fetchColumn("SELECT id FROM ExpressEntityEntryAssociations ORDER BY id DESC LIMIT 1;");

        $targetAssociations = [];

        foreach ($db->fetchAll("SELECT * FROM ExpressEntityAssociations WHERE association_type = 'owning';") as $row) {
            if (!isset($targetAssociations[$row["target_entity_id"]])) {
                $targetAssociations[$row["target_entity_id"]] = [];
            }

            $targetAssociations[$row["source_entity_id"]][$row["target_property_name"]] = [
                "id" => $row["id"],
                "type" => $row["type"]
            ];
        }

        $sourceAssociations = [];

        foreach ($db->fetchAll("SELECT * FROM ExpressEntityAssociations WHERE association_type = 'inverse';") as $row) {
            if (!isset($sourceAssociations[$row["target_entity_id"]])) {
                $sourceAssociations[$row["inversed_by_property_name"]] = [];
            }

            $sourceAssociations[$row["target_entity_id"]][$row["inversed_by_property_name"]] = [
                "id" => $row["id"],
                "type" => $row["type"]
            ];
        }

        foreach ($db->fetchAll("SELECT ak.akID, ak.akHandle, at.atHandle FROM AttributeKeys AS ak LEFT JOIN ExpressAttributeKeys as eak ON (ak.akID = eak.akID) LEFT JOIN AttributeTypes as at ON (ak.atID = at.atID) WHERE eak.entity_id = ?", array($entityId)) as $row) {
            $attributeKeyIds[$row["akHandle"]] = $row["akID"];
            $attributeTables[$row["akHandle"]] = "at" . str_replace("_", "", ucwords("_" . $row["atHandle"], "_"));

            try {
                foreach ($db->fetchAll("SHOW COLUMNS FROM " . $attributeTables[$row["akHandle"]]) as $secondRow) {
                    if ($secondRow["Field"] !== "avID") {
                        $attributeTableFields[$attributeTables[$row["akHandle"]]][] = $secondRow["Field"];
                    }
                }
            } catch (\Doctrine\DBAL\Exception\TableNotFoundException $exception) {
                $attributeTables[$row["akHandle"]] = "atDefault";
                $attributeTableFields[$attributeTables[$row["akHandle"]]] = ["avID", "value"];
            }
        }

        $i = 0;

        foreach ($entries as $entry) {
            $i++;

            $entryId++;

            $insertQueries["ExpressEntityEntries"][] = [
                "exEntryID" => $entryId,
                "exEntryDisplayOrder" => 0,
                "exEntryDateCreated" => "NOW()",
                "exEntryEntityID" => $entityId
            ];

            $searchIndexInsertData = [
                "exEntryID" => $entryId
            ];

            foreach ($entry as $column => $cell) {
                $attributeValueId++;

                if (isset($attributeKeyIds[$column])) {
                    $insertQueries["AttributeValues"][] = [
                        "avID" => $attributeValueId,
                        "akID" => $attributeKeyIds[$column]
                    ];

                    $insertQueries["ExpressEntityEntryAttributeValues"][] = [
                        "exEntryID" => $entryId,
                        "akID" => $attributeKeyIds[$column],
                        "avID" => $attributeValueId
                    ];

                    $attributeFields = [
                        "avID" => $attributeValueId
                    ];

                    if (is_array($cell)) {
                        foreach ($attributeTableFields[$attributeTables[$column]] as $fieldName) {
                            if (isset($cell[$fieldName])) {
                                $attributeFields[$fieldName] = $cell[$fieldName];
                            }
                        }
                    } else {
                        $attributeFields["value"] = $cell;

                        $searchIndexInsertData["ak_" . addslashes($column)] = $cell;
                    }

                    $insertQueries[$attributeTables[$column]][] = $attributeFields;
                } else {
                    $sourceColumn = $db->fetchColumn("SELECT e.handle FROM ExpressEntities as e LEFT JOIN ExpressEntityAssociations AS eea ON (e.id = eea.source_entity_id) WHERE eea.inversed_by_property_name = ?;", array($column));

                    $associatedTable = str_replace("_", "", ucwords($sourceColumn, "_")) . "ExpressSearchIndexAttributes";;

                    foreach ($cell as $association) {
                        foreach ($association as $associatedKey => $associatedValue) {
                            $associatedEntryId = (int)$db->fetchColumn(
                                sprintf(
                                    "SELECT exEntryID FROM %s WHERE ak_%s = ?",
                                    addslashes($associatedTable),
                                    addslashes($associatedKey)
                                ),

                                array(
                                    $associatedValue
                                )
                            );

                            if ($associatedEntryId > 0) {
                                $associationEntryId++;

                                $insertQueries["ExpressEntityAssociationSelectedEntries"][] = [
                                    "id" => $associationEntryId,
                                    "exSelectedEntryID" => $associatedEntryId
                                ];

                                $insertQueries["ExpressEntityEntryAssociations"][] = [
                                    "id" => $associationEntryId,
                                    "association_id" => $targetAssociations[$entityId][$column]["id"],
                                    "exEntryID" => $entryId,
                                    "type" => $targetAssociations[$entityId][$column]["type"] === "onetooneassociation" ? "oneassociation" : "manyassociation"
                                ];

                                $associationEntryId++;

                                $insertQueries["ExpressEntityAssociationSelectedEntries"][] = [
                                    "id" => $associationEntryId,
                                    "exSelectedEntryID" => $entryId
                                ];

                                $insertQueries["ExpressEntityEntryAssociations"][] = [
                                    "id" => $associationEntryId,
                                    "association_id" => $sourceAssociations[$entityId][$column]["id"],
                                    "exEntryID" => $associatedEntryId,
                                    "type" => $sourceAssociations[$entityId][$column]["type"] === "onetooneassociation" ? "oneassociation" : "manyassociation"
                                ];
                            }
                        }
                    }
                }
            }

            $insertQueries[$searchIndexTable][] = $searchIndexInsertData;

            if ($i % $batchSize === 0 || $i === count($entries)) {
                foreach ($insertQueries as $tableName => $rows) {
                    $columns = array_keys($rows[0]);

                    $sql = "INSERT INTO `" . $tableName . "` (";

                    $z = 0;

                    foreach($columns as $curColumn) {
                        $z++;

                        $sql .= "`" . $curColumn . "`";

                        if ($z < count($columns)) {
                            $sql .= ", ";
                        }
                    }

                    $sql .= ") VALUES ";

                    $n = 0;

                    foreach ($rows as $row) {
                        $n++;

                        $sql .= "(";

                        $c = 0;

                        foreach ($row as $cell) {
                            $c++;

                            if (strtoupper($cell) === "NOW()") {
                                $sql .= $cell;
                            } elseif (is_numeric($cell)) {
                                $sql .= $cell + 0;
                            } else {
                                $sql .= "'" . addslashes($cell) . "'";
                            }

                            if ($c < count($row)) {
                                $sql .= ", ";
                            }
                        }

                        $sql .= ")";

                        if ($n < count($rows)) {
                            $sql .= ", ";
                        }
                    }

                    $sql .= ";";

                    $db->executeQuery("SET foreign_key_checks = 0");

                    $db->executeQuery($sql);

                    $db->executeQuery("SET foreign_key_checks = 1");

                }

                $insertQueries = [];

                $insertQueries["AttributeValues"] = [];
                $insertQueries["ExpressEntityEntries"] = [];
                $insertQueries["ExpressEntityEntryAttributeValues"] = [];
                $insertQueries[$searchIndexTable] = [];
            }
        }
    }
}
