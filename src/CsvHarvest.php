<?php
namespace Streamvest;

use SplFileObject;

class CsvHarvest
{
    /**
     * @param string $input filename
     * @param string $output filename
     */
    public function transform($input, $output)
    {
        $inputFile = new SplFileObject($input);
        $inputFile->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        $outputFile = new SplFileObject($output, 'w+');
        $outputFile->setCsvControl(',');

        $data = [];
        $tasks = [];

        $headers = $inputFile->fgetcsv();
        while(!$inputFile->eof()) {
            $row = $inputFile->fgetcsv();
            $date = $row[0];
            $task = $row[4];

            if (empty($row)) {
                continue;
            }

            if (!isset($data[$date])) {
                $data[$date]['values'] = [];
                $data[$date]['tasks'] = [];
                $data[$date]['_tasks'] = [];
            }

            if (!in_array($task, $data[$date]['tasks'])) {
                $data[$date]['tasks'][] = $task;
            }

            if (!in_array($task, $tasks)) {
                $tasks[] = $task;
            }

            //@FIXME: ugly things here...
            if (!in_array($task, $data[$date]['_tasks'])) {
                $data[$date]['values'][] = $row;
            } else {
                // merge with precedent row of the same task
                for($i=0, $n=count($data[$date]['values']); $i<$n; $i++) {
                    if ($data[$date]['values'][$i] == $task) {
                        $data[$date]['values'][$i][6] += $row[6];
                    }
                }
            }
            $data[$date]['_tasks'][] = $task;

        }

        $firstRow = reset($data);
        $firstRow = $firstRow['values'][0];
        $dummyRow = array_replace($firstRow, array(
            6 => 0,         // Hours
            11 => 'Nobody', // firstname
            12 => 'Nobody'  // lastname
        ));

        // fill with dummy values
        $maxTasks = count($tasks);
        foreach($data as $date => &$d) {
            $c = count($d['tasks']);
            if($c < $maxTasks) {
                $missingTasks = array_diff($tasks, $d['tasks']);
                foreach($missingTasks as $task) {
                    $d['values'][] = array_replace($dummyRow, array(
                        0 => $date,
                        4 => $task,
                    ));
                    $d['tasks'][] = $task;
                }
            }
        }
        unset($d);


        $outputFile->fputcsv($headers);
        foreach($data as $date => $d) {
            foreach($d['values'] as $row) {
                $outputFile->fputcsv($row);
            }
        }

    }
}
