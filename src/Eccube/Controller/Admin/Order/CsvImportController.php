<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eccube\Controller\Admin\Order;

use Eccube\Controller\Admin\AbstractCsvImportController;
use Eccube\Entity\Csv;
use Eccube\Entity\Master\CsvType;
use Eccube\Entity\Master\OrderStatus;
use Eccube\Entity\Shipping;
use Eccube\Form\Type\Admin\CsvImportType;
use Eccube\Repository\CsvRepository;
use Eccube\Repository\Master\CsvTypeRepository;
use Eccube\Repository\ShippingRepository;
use Eccube\Service\CsvImportService;
use Eccube\Service\OrderStateMachine;
use Eccube\Util\StringUtil;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CsvImportController extends AbstractCsvImportController
{
    /**
     * @var ShippingRepository
     */
    private $shippingRepository;

    /**
     * @var CsvTypeRepository
     */
    private $csvTypeRepository;
    /**
     * @var CsvRepository
     */
    private $csvRepository;

    /**
     * @var OrderStateMachine
     */
    protected $orderStateMachine;

    /**
     * CsvImportController constructor.
     * @param ShippingRepository $shippingRepository
     * @param CsvTypeRepository $csvTypeRepository
     * @param CsvRepository $csvRepository
     * @param OrderStateMachine $orderStateMachine
     */
    public function __construct(
        ShippingRepository $shippingRepository,
        CsvTypeRepository $csvTypeRepository,
        CsvRepository $csvRepository,
        OrderStateMachine $orderStateMachine
    ) {
        $this->shippingRepository = $shippingRepository;
        $this->csvTypeRepository = $csvTypeRepository;
        $this->csvRepository = $csvRepository;
        $this->orderStateMachine = $orderStateMachine;
    }


    /**
     * 出荷CSVアップロード
     *
     * @Route("/%eccube_admin_route%/order/shipping_csv_upload", name="admin_shipping_csv_import")
     * @Template("@admin/Order/csv_shipping.twig")
     *
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function csvShipping(Request $request)
    {
        $form = $this->formFactory->createBuilder(CsvImportType::class)->getForm();
        $columnConfig = $this->getColumnConfig();
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);
            if ($form->isValid()) {
                $formFile = $form['import_file']->getData();

                if (!empty($formFile)) {
                    $csv = $this->getImportData($formFile);

                    try {
                        $this->entityManager->getConfiguration()->setSQLLogger(null);
                        $this->entityManager->getConnection()->beginTransaction();

                        $this->loadCsv($csv, $errors);

                        if ($errors) {
                            $this->entityManager->getConnection()->rollBack();
                        } else {
                            $this->entityManager->flush();
                            $this->entityManager->getConnection()->commit();

                            $this->addInfo('admin.common.csv_upload_complete', 'admin');
                        }
                    } finally {
                        $this->removeUploadedFile();
                    }
                }
            }
        }

        return [
            'form' => $form->createView(),
            'headers' => $columnConfig,
            'errors' => $errors,
        ];
    }

    protected function loadCsv(CsvImportService $csv, &$errors)
    {
        $columnConfig = $this->getColumnConfig();
        if ($csv === false) {
            $errors[] = trans('admin.common.csv_invalid_format');
        }

        // 必須カラムの確認
        $requiredColumns = array_map(function ($value) {
            return $value['display_name'];
        }, array_filter($columnConfig, function ($value) {
            return $value['required'];
        }));
        $csvColumns = $csv->getColumnHeaders();

        if (count(array_diff($requiredColumns, $csvColumns)) > 0) {
            $errors[] = trans('admin.common.csv_invalid_format');

            return;
        }

        // 行数の確認
        $size = count($csv);
        if ($size < 1) {
            $errors[] = trans('admin.common.csv_invalid_format');

            return;
        }
        $columnNames = array_combine(array_keys($columnConfig), array_column($columnConfig, 'display_name'));
        foreach ($csv as $line => $row) {
            // 出荷IDがなければエラー
            if (!isset($row[$columnNames['id']])) {
                $errors[] = trans('admin.common.csv_invalid_required', ['%line%' => $line, '%name%' => $columnNames['id']]);
                continue;
            }

            /* @var Shipping $Shipping */
            $Shipping = is_numeric($row[$columnNames['id']]) ? $this->shippingRepository->find($row[$columnNames['id']]) : null;

            // 存在しない出荷IDはエラー
            if (is_null($Shipping)) {
                $errors[] = trans('admin.common.csv_invalid_not_found', ['%line%' => $line, '%name%' => $columnNames['id']]);
                continue;
            }

            if (isset($row[$columnNames['tracking_number']])) {
                $Shipping->setTrackingNumber($row[$columnNames['tracking_number']]);
            }

            if (isset($row[$columnNames['shipping_date']])) {
                // 日付フォーマットが異なる場合はエラー
                $shippingDate = \DateTime::createFromFormat($this->eccubeConfig->get('eccube_csv_export_date_format'), $row[$columnNames['shipping_date']]);
                if ($shippingDate === false) {
                    $errors[] = trans('admin.common.csv_invalid_date_format', ['%line%' => $line, '%name%' => $columnNames['id']]);
                    continue;
                }

                $shippingDate->setTime(0, 0, 0);
                $Shipping->setShippingDate($shippingDate);
            }

            $Order = $Shipping->getOrder();
            $RelateShippings = $Order->getShippings();
            $allShipped = true;
            foreach ($RelateShippings as $RelateShipping) {
                if (!$RelateShipping->getShippingDate()) {
                    $allShipped = false;
                    break;
                }
            }
            $OrderStatus = $this->entityManager->find(OrderStatus::class, OrderStatus::DELIVERED);
            if ($allShipped) {
                if ($this->orderStateMachine->can($Order, $OrderStatus)) {
                    $this->orderStateMachine->apply($Order, $OrderStatus);
                } else {
                    $from = $Order->getOrderStatus()->getName();
                    $to = $OrderStatus->getName();
                    $errors[] = trans('admin.order.failed_to_change_status', [
                        '%name%' => $Shipping->getId(),
                        '%from%' => $from,
                        '%to%' => $to,
                    ]);
                }
            }
        }
    }

    /**
     * アップロード用CSV雛形ファイルダウンロード
     *
     * @Route("/%eccube_admin_route%/order/csv_template", name="admin_shipping_csv_template")
     */
    public function csvTemplate(Request $request)
    {
        $columns = array_column($this->getColumnConfig(), 'name');

        return $this->sendTemplateResponse($request, $columns, 'shipping.csv');
    }

    protected function getColumnConfig()
    {
        $data = [
            'id' => [
                'name' => trans('admin.order.shipping_csv.shipping_id_col'),
                'description' => trans('admin.order.shipping_csv.shipping_id_description'),
                'required' => true,
            ],
            'tracking_number' => [
                'name' => trans('admin.order.shipping_csv.tracking_number_col'),
                'description' => trans('admin.order.shipping_csv.tracking_number_description'),
                'required' => false,
            ],
            'shipping_date' => [
                'name' => trans('admin.order.shipping_csv.shipping_date_col'),
                'description' => trans('admin.order.shipping_csv.shipping_date_description'),
                'required' => true,
            ],
        ];

        $csvType = $this->csvTypeRepository->find(CsvType::CSV_TYPE_SHIPPING);
        /** @var Csv[] $csvs */
        $csvs = $this->csvRepository->findBy(['CsvType' => $csvType]);
        $data = $this->getDisplayNameByCsv($data, $csvs);

        return $data;
    }

    /**
     * @param $oldData
     * @param $productCsv
     * @return mixed
     */
    private function getDisplayNameByCsv($oldData, $productCsv)
    {
        foreach ($oldData as $datum => $oldDatum) {
            $oldData[$datum]['display_name'] = $datum;
            /**
             * @var  $index
             * @var Csv $csv
             */
            foreach ($productCsv as $index => $csv) {
                $key = StringUtil::toUnderscores($csv->getFieldName());
                if ($key == $datum) {
                    if (is_null($csv->getReferenceFieldName())
                        || $csv->getReferenceFieldName() == 'id'
                        || strpos($csv->getReferenceFieldName(), '_id')
                        || $csv->getReferenceFieldName() == 'file_name'
                    ) {
                        $oldData[$datum]['display_name'] = $csv->getDispName();
                        break;
                    }
                }
            }
        }

        return $oldData;
    }
}
