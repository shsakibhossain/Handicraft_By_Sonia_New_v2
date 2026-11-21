<?php

session_start();

require_once "config/database.php";


/* ==========================================
   CHECK ORDER ID
========================================== */

if (
    !isset($_GET['order_id']) ||
    !is_numeric($_GET['order_id'])
) {

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
     WHERE order_id = :order_id
     ORDER BY id ASC"
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Order Successful - Handicraft by Sonia
    </title>


    <!-- Google Fonts -->

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >


    <!-- PRINT STYLE -->

    <style>

        .print-order-area {
            margin-top: 25px;
            padding: 25px;
            background: #ffffff;
            border-radius: 15px;
            text-align: left;
        }


        .print-order-area h2 {
            margin-bottom: 20px;
            text-align: center;
        }


        .print-order-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }


        .print-order-table th,
        .print-order-table td {
            padding: 10px;
            border-bottom: 1px solid #eeeeee;
        }


        .print-order-table th {
            text-align: left;
        }


        .print-order-table td:last-child,
        .print-order-table th:last-child {
            text-align: right;
        }


        .order-summary-print {
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }


        .order-summary-print p {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }


        .order-total-print {
            font-size: 20px;
            font-weight: 700;
            padding-top: 10px;
            border-top: 2px solid #222;
        }


        .order-buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }


        .print-order-btn,
        .view-order-btn,
        .continue-shopping-btn {
            border: none;
            text-decoration: none;
            cursor: pointer;
            padding: 12px 22px;
            border-radius: 8px;
            font-family: Poppins, sans-serif;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }


        .print-order-btn {
            background: #5b21b6;
            color: white;
        }


        .view-order-btn {
            background: #f3f0ff;
            color: #5b21b6;
        }


        .continue-shopping-btn {
            background: #222;
            color: white;
        }


        /* ==========================================
           PRINT VERSION
        ========================================== */

        @media print {

            body {
                background: white !important;
            }


            .order-success-section {
                padding: 0 !important;
            }


            .order-success-box {
                box-shadow: none !important;
                max-width: 800px !important;
                margin: 0 auto !important;
            }


            .success-icon,
            .order-buttons,
            .success-message {
                display: none !important;
            }


            .order-success-box h1 {
                font-size: 24px;
            }


            .print-order-area {
                box-shadow: none !important;
                padding: 0 !important;
            }


            .no-print {
                display: none !important;
            }

        }

    </style>

</head>


<body>


<section class="order-success-section">


    <div class="order-success-box">


        <!-- SUCCESS ICON -->

        <div class="success-icon">

            <i class="bi bi-check-circle-fill"></i>

        </div>


        <!-- SUCCESS MESSAGE -->

        <h1>

            Order Placed Successfully!

        </h1>


        <p class="success-message">

            Thank you for shopping with
            Handicraft by Sonia.

        </p>


        <!-- ORDER NUMBER -->

        <div class="order-number">

            Order ID:

            <strong>

                #<?php
                echo $order['id'];
                ?>

            </strong>

        </div>


        <!-- ORDER DETAILS -->

        <div class="success-details">


            <p>

                <strong>
                    Customer:
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

                <?php
                echo htmlspecialchars(
                    $order['order_status']
                );
                ?>

            </p>


        </div>


        <!-- ==========================================
             PRINTABLE ORDER
        ========================================== -->

        <div class="print-order-area">


            <h2>
                Order Summary
            </h2>


            <table class="print-order-table">


                <thead>

                    <tr>

                        <th>
                            Product
                        </th>

                        <th>
                            Qty
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Total
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php foreach (
                        $order_items
                        as $item
                    ): ?>


                        <tr>

                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $item['product_name']
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
                                    $item['price'],
                                    0
                                );

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


            <!-- SUMMARY -->

            <div class="order-summary-print">


                <p>

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

                </p>


                <p>

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

                </p>


                <p class="order-total-print">

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

                </p>


            </div>


            <!-- DELIVERY ADDRESS -->

            <div style="margin-top: 20px;">

                <strong>
                    Delivery Address
                </strong>

                <p>

                    <?php
                    echo htmlspecialchars(
                        $order['address']
                    );
                    ?>

                    <br>

                    <?php
                    echo htmlspecialchars(
                        $order['area']
                    );
                    ?>,

                    <?php
                    echo htmlspecialchars(
                        $order['city']
                    );
                    ?>

                </p>

            </div>


        </div>


        <!-- ==========================================
             BUTTONS
        ========================================== -->

        <div class="order-buttons no-print">


            <!-- PRINT -->

            <button
                type="button"
                class="print-order-btn"
                onclick="window.print()"
            >

                <i class="bi bi-printer"></i>

                Print Order

            </button>


            <!-- VIEW ORDER -->

            <a
                href="my-orders.php?order_id=<?php
                echo $order['id'];
                ?>"
                class="view-order-btn"
            >

                <i class="bi bi-receipt"></i>

                View Order

            </a>


            <!-- CONTINUE SHOPPING -->

            <a
                href="shop.php"
                class="continue-shopping-btn"
            >

                <i class="bi bi-shop"></i>

                Continue Shopping

            </a>


        </div>


    </div>

</section>


</body>

</html>