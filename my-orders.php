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


/* Order not found */

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


/* ==========================================
   PAYMENT METHOD DISPLAY
========================================== */

$payment_method = $order['payment_method'];

if ($payment_method === 'cash_on_delivery') {

    $payment_display = 'Cash on Delivery';

} elseif ($payment_method === 'bkash') {

    $payment_display = 'bKash';

} elseif ($payment_method === 'nagad') {

    $payment_display = 'Nagad';

} elseif ($payment_method === 'rocket') {

    $payment_display = 'Rocket';

} else {

    $payment_display = ucfirst(
        str_replace('_', ' ', $payment_method)
    );

}

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
        My Order - Handicraft by Sonia
    </title>


    <!-- Google Fonts -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

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


    <!-- Small Payment Card Style -->

    <style>

        .order-payment-card {

            margin-top: 25px;
            padding: 25px;
            background: #ffffff;
            border-radius: 15px;

        }


        .order-payment-card h2 {

            margin-bottom: 18px;

        }


        .order-payment-card p {

            margin: 10px 0;
            line-height: 1.6;

        }


        .order-payment-card strong {

            margin-right: 5px;

        }


        .payment-status-text {

            color: #555;

        }

    </style>

</head>


<body>


<!-- ================= HEADER ================= -->

<header class="main-header">

    <div class="logo-area">

        <img
            src="assets/images/images.jpeg"
            alt="Handicraft by Sonia"
        >

        <div class="logo-text">

            <h2>
                Handicraft
            </h2>

            <span>
                by Sonia
            </span>

        </div>

    </div>


    <nav class="navbar">

        <a href="index.php">
            Home
        </a>

        <a href="shop.php">
            Shop
        </a>

        <a href="categories.php">
            Categories
        </a>

        <a href="custom-order.php">
            Custom Order
        </a>

        <a href="about.php">
            About Us
        </a>

        <a href="contact.php">
            Contact Us
        </a>

        <a href="faq.php">
            FAQ
        </a>

    </nav>


    <div class="header-actions">

        <a
            href="login.php"
            class="icon-link"
        >

            <i class="bi bi-person"></i>

        </a>


        <a
            href="wishlist.php"
            class="icon-link notification"
        >

            <i class="bi bi-heart"></i>

            <span>
                0
            </span>

        </a>


        <a
            href="cart.php"
            class="icon-link notification"
        >

            <i class="bi bi-cart3"></i>

            <span>
                0
            </span>

        </a>


        <a
            href="login.php"
            class="login-btn"
        >

            <i class="bi bi-person"></i>

            Login

        </a>

    </div>

</header>


<!-- ================= ORDER HEADER ================= -->

<section class="order-page-header">

    <span>
        ORDER DETAILS
    </span>

    <h1>
        My Order
    </h1>

    <p>
        Thank you for shopping with Handicraft by Sonia.
    </p>

</section>


<!-- ================= ORDER DETAILS ================= -->

<section class="my-order-section">

    <div class="my-order-container">


        <!-- ==========================================
             ORDER INFORMATION
        ========================================== -->

        <div class="order-info-card">


            <!-- Order ID -->

            <div>

                <span>
                    Order ID
                </span>

                <strong>

                    #<?php
                    echo $order['id'];
                    ?>

                </strong>

            </div>


            <!-- Order Date -->

            <div>

                <span>
                    Order Date
                </span>

                <strong>

                    <?php

                    echo date(
                        'd M Y, h:i A',
                        strtotime(
                            $order['created_at']
                        )
                    );

                    ?>

                </strong>

            </div>


            <!-- Order Status -->

            <div>

                <span>
                    Order Status
                </span>

                <strong class="order-status">

                    <?php

                    echo htmlspecialchars(
                        $order['order_status']
                    );

                    ?>

                </strong>

            </div>


            <!-- Payment -->

            <div>

                <span>
                    Payment
                </span>

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $payment_display
                    );

                    ?>

                </strong>

            </div>


            <!-- Total -->

            <div>

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
             CUSTOMER INFORMATION
        ========================================== -->

        <div class="order-customer-card">

            <h2>
                Delivery Information
            </h2>


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


        <!-- ==========================================
             PAYMENT DETAILS
        ========================================== -->

        <div class="order-payment-card">

            <h2>
                Payment Details
            </h2>


            <p>

                <strong>
                    Payment Method:
                </strong>

                <?php

                echo htmlspecialchars(
                    $payment_display
                );

                ?>

            </p>


            <?php if (
                $payment_method !== 'cash_on_delivery'
            ): ?>


                <p>

                    <strong>
                        Payment Number:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['payment_number']
                    );

                    ?>

                </p>


                <p>

                    <strong>
                        Transaction ID:
                    </strong>

                    <?php

                    echo htmlspecialchars(
                        $order['transaction_id']
                    );

                    ?>

                </p>


            <?php else: ?>


                <p class="payment-status-text">

                    <strong>
                        Payment Status:
                    </strong>

                    Cash will be collected on delivery.

                </p>


            <?php endif; ?>


        </div>


        <!-- ==========================================
             ORDERED PRODUCTS
        ========================================== -->

        <div class="order-items-card">

            <h2>
                Ordered Products
            </h2>


            <?php if (!empty($order_items)): ?>


                <?php foreach (
                    $order_items
                    as $item
                ): ?>


                    <div class="my-order-item">


                        <div>

                            <h3>

                                <?php

                                echo htmlspecialchars(
                                    $item['product_name']
                                );

                                ?>

                            </h3>


                            <p>

                                Quantity:

                                <?php

                                echo (int)
                                    $item['quantity'];

                                ?>

                            </p>


                            <p>

                                Unit Price:

                                ৳<?php

                                echo number_format(
                                    $item['price'],
                                    0
                                );

                                ?>

                            </p>

                        </div>


                        <strong>

                            ৳<?php

                            echo number_format(
                                $item['subtotal'],
                                0
                            );

                            ?>

                        </strong>

                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <p>
                    No order items found.
                </p>


            <?php endif; ?>


        </div>


        <!-- ==========================================
             PRICE SUMMARY
        ========================================== -->

        <div class="order-price-card">


            <!-- Subtotal -->

            <div>

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


            <!-- Delivery -->

            <div>

                <span>
                    Delivery
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


            <!-- Total -->

            <div class="order-total-row">

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
             ACTION BUTTONS
        ========================================== -->

        <div class="order-page-actions">


            <!-- Continue Shopping -->

            <a href="shop.php">

                <i class="bi bi-arrow-left"></i>

                Continue Shopping

            </a>


            <!-- Print Order -->

            <a
                href="print-order.php?order_id=<?php
                echo $order['id'];
                ?>"
                target="_blank"
            >

                <i class="bi bi-printer"></i>

                Print Order

            </a>


        </div>


    </div>

</section>


<!-- ================= FOOTER ================= -->

<footer>

    <p>

        © 2026 Handicraft by Sonia |

        All Rights Reserved By Team Triple Pixel

    </p>

</footer>


</body>

</html>