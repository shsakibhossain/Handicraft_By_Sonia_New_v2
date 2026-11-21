<?php

session_start();

require_once "config/database.php";


/* ==========================================
   CHECK ORDER ID
========================================== */

if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {

    header("Location: shop.php");
    exit;

}

$order_id = (int) $_GET['order_id'];


/* ==========================================
   GET ORDER
========================================== */

$stmt = $pdo->prepare(
    "SELECT *
     FROM orders
     WHERE id = :id
     LIMIT 1"
);

$stmt->execute([
    'id' => $order_id
]);

$order = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$order) {

    header("Location: shop.php");
    exit;

}


/* ==========================================
   GET ORDER ITEMS
========================================== */

$item_stmt = $pdo->prepare(
    "SELECT *
     FROM order_items
     WHERE order_id = :order_id"
);

$item_stmt->execute([
    'order_id' => $order_id
]);

$order_items = $item_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Order #<?php echo $order['id']; ?> -
        Handicraft by Sonia
    </title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 30px;
            background: #eeeeee;
            font-family: Arial, sans-serif;
            color: #333;
        }


        .print-wrapper {
            max-width: 800px;
            margin: auto;
        }


        /* ==========================================
           INVOICE
        ========================================== */

        .invoice {
            background: white;
            padding: 40px;
            min-height: 1050px;
        }


        /* ==========================================
           HEADER
        ========================================== */

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #5b21b6;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }


        .brand h1 {
            margin: 0;
            color: #5b21b6;
            font-size: 28px;
        }


        .brand p {
            margin: 5px 0 0;
            color: #777;
            font-size: 13px;
        }


        .invoice-title {
            text-align: right;
        }


        .invoice-title h2 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }


        .invoice-title p {
            margin: 5px 0;
            color: #777;
            font-size: 13px;
        }


        /* ==========================================
           CUSTOMER + ORDER INFO
        ========================================== */

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }


        .info-box {
            border: 1px solid #ddd;
            padding: 18px;
            border-radius: 7px;
        }


        .info-box h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: #5b21b6;
        }


        .info-box p {
            margin: 6px 0;
            font-size: 13px;
            line-height: 1.5;
        }


        /* ==========================================
           PRODUCT TABLE
        ========================================== */

        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }


        .product-table th {
            background: #5b21b6;
            color: white;
            padding: 10px;
            font-size: 13px;
            text-align: left;
        }


        .product-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }


        .product-table th:last-child,
        .product-table td:last-child {
            text-align: right;
        }


        .product-table th:nth-child(3),
        .product-table td:nth-child(3) {
            text-align: center;
        }


        /* ==========================================
           SUMMARY
        ========================================== */

        .summary {
            width: 300px;
            margin-left: auto;
            margin-top: 25px;
        }


        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 7px 0;
            font-size: 13px;
        }


        .summary-total {
            border-top: 2px solid #5b21b6;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 17px;
            font-weight: bold;
            color: #5b21b6;
        }


        /* ==========================================
           STATUS
        ========================================== */

        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            background: #fff3cd;
            color: #856404;
            font-size: 12px;
        }


        /* ==========================================
           FOOTER
        ========================================== */

        .invoice-footer {
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 12px;
        }


        /* ==========================================
           PRINT BUTTON
           BUTTON IS NOW AT THE BOTTOM
        ========================================== */

        .print-button-area {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
        }


        .print-button {
            border: none;
            background: #5b21b6;
            color: white;
            padding: 12px 25px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            transition: 0.3s;
        }


        .print-button:hover {
            background: #4c1d95;
        }


        /* ==========================================
           PRINT SETTINGS
        ========================================== */

        @media print {

            @page {
                size: A4;
                margin: 10mm;
            }


            body {
                background: white;
                padding: 0;
            }


            .print-wrapper {
                max-width: none;
            }


            /* Hide Print Button While Printing */

            .print-button-area {
                display: none;
            }


            .invoice {
                padding: 10px;
                min-height: auto;
            }


            .invoice-header {
                margin-bottom: 18px;
            }


            .info-section {
                margin-bottom: 15px;
            }


            .product-table {
                margin-top: 10px;
            }


            .invoice-footer {
                margin-top: 20px;
            }

        }


        /* ==========================================
           MOBILE RESPONSIVE
        ========================================== */

        @media (max-width: 600px) {

            body {
                padding: 10px;
            }


            .invoice {
                padding: 20px;
            }


            .invoice-header {
                flex-direction: column;
                gap: 15px;
            }


            .invoice-title {
                text-align: left;
            }


            .info-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }


            .summary {
                width: 100%;
            }


            .product-table {
                font-size: 12px;
            }


            .product-table th,
            .product-table td {
                padding: 7px 5px;
            }


            .brand h1 {
                font-size: 23px;
            }


            .invoice-title h2 {
                font-size: 20px;
            }

        }

    </style>

</head>


<body>


<div class="print-wrapper">


    <!-- ==========================================
         INVOICE
    ========================================== -->

    <div class="invoice">


        <!-- ==========================================
             HEADER
        ========================================== -->

        <div class="invoice-header">


            <div class="brand">

                <h1>
                    Handicraft by Sonia
                </h1>

                <p>
                    Handmade With Love
                </p>

            </div>


            <div class="invoice-title">

                <h2>
                    ORDER RECEIPT
                </h2>

                <p>
                    Order #<?php echo $order['id']; ?>
                </p>

                <p>

                    <?php

                    if (!empty($order['created_at'])) {

                        echo date(
                            'd M Y',
                            strtotime(
                                $order['created_at']
                            )
                        );

                    }

                    ?>

                </p>

            </div>


        </div>


        <!-- ==========================================
             CUSTOMER INFORMATION
        ========================================== -->

        <div class="info-section">


            <div class="info-box">

                <h3>
                    Customer Information
                </h3>


                <p>

                    <strong>
                        Name:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['customer_name']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Phone:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['phone']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Address:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['address']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        City:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['city']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Area:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['area']
                    );

                    ?>

                </p>


            </div>


            <div class="info-box">

                <h3>
                    Order Information
                </h3>


                <p>

                    <strong>
                        Order ID:
                    </strong>

                    #<?php

                    echo $order['id'];

                    ?>

                </p>


                <p>

                    <strong>
                        Payment:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['payment_method']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Status:
                    </strong>


                    <span class="status">

                        <?php

                        echo htmlspecialchars(
                            $order['order_status']
                        );

                        ?>

                    </span>

                </p>


            </div>


        </div>


        <!-- ==========================================
             PRODUCTS
        ========================================== -->

        <table class="product-table">


            <thead>

                <tr>

                    <th>
                        Product
                    </th>

                    <th>
                        Price
                    </th>

                    <th>
                        Qty
                    </th>

                    <th>
                        Subtotal
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php foreach ($order_items as $item): ?>


                    <tr>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $item['product_name']
                            );

                            ?>

                        </td>


                        <td>

                            ৳<?php

                            echo number_format(
                                $item['price'],
                                0
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo (int)
                            $item['quantity'];

                            ?>

                        </td>


                        <td>

                            ৳<?php

                            echo number_format(
                                $item['subtotal'],
                                0
                            );

                            ?>

                        </td>


                    </tr>


                <?php endforeach; ?>


            </tbody>


        </table>


        <!-- ==========================================
             SUMMARY
        ========================================== -->

        <div class="summary">


            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong>

                    ৳<?php

                    echo number_format(
                        $order['subtotal'],
                        0
                    );

                    ?>

                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Delivery Charge
                </span>

                <strong>

                    ৳<?php

                    echo number_format(
                        $order['delivery_charge'],
                        0
                    );

                    ?>

                </strong>

            </div>


            <div class="summary-row summary-total">

                <span>
                    Total
                </span>

                <strong>

                    ৳<?php

                    echo number_format(
                        $order['total_amount'],
                        0
                    );

                    ?>

                </strong>

            </div>


        </div>


        <!-- ==========================================
             FOOTER
        ========================================== -->

        <div class="invoice-footer">


            <p>

                Thank you for shopping with

                <strong>
                    Handicraft by Sonia
                </strong>

            </p>


            <p>
                Handmade With Love ❤️
            </p>


        </div>


        <!-- ==========================================
             PRINT BUTTON
             NOW AT THE VERY BOTTOM
        ========================================== -->

        <div class="print-button-area">


            <button
                onclick="window.print()"
                class="print-button">


                <i class="bi bi-printer"></i>


                Print Order


            </button>


        </div>


    </div>


</div>


</body>

</html>
```
