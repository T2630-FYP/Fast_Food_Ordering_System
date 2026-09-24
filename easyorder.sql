-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 14, 2026 at 11:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `easyorder`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `cart_member` int(11) NOT NULL,
  `cart_product` char(5) NOT NULL,
  `cart_qty` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` char(5) NOT NULL,
  `category_name` varchar(50) NOT NULL,
  `category_desc` varchar(100) NOT NULL,
  `category_status` varchar(10) NOT NULL,
  `category_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`, `category_desc`, `category_status`, `category_isDelete`) VALUES
('C001', 'Fried Chicken', 'Crispy, juicy and golden fried chicken.', 'Active', 0),
('C002', 'Burger', 'Juicy burgers stacked with fresh ingredients.', 'Active', 0),
('C003', 'Side Dishes', 'The perfect sides to complete your meal.', 'Active', 0),
('C004', 'Dessert', 'Sweet treats to end your meal on a high note.', 'Active', 0),
('C005', 'Beverage', 'Cold drinks to keep you refreshed.', 'Active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `contact_msg`
--

CREATE TABLE `contact_msg` (
  `msg_id` int(11) NOT NULL,
  `msg_name` varchar(100) NOT NULL,
  `msg_email` varchar(100) NOT NULL,
  `msg_subject` varchar(50) NOT NULL,
  `msg_message` text NOT NULL,
  `msg_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `contact_msg`
--

INSERT INTO `contact_msg` (`msg_id`, `msg_name`, `msg_email`, `msg_subject`, `msg_message`, `msg_date`) VALUES
(1, 'Lim Ah Kao', 'ahkao@email.com', 'General Enquiry', 'Do you provide catering for office events?', '2026-05-20');

-- --------------------------------------------------------

--
-- Table structure for table `member`
--

CREATE TABLE `member` (
  `member_id` int(11) NOT NULL,
  `member_name` varchar(100) NOT NULL,
  `member_email` varchar(100) NOT NULL,
  `member_password` varchar(255) NOT NULL,
  `member_phone` varchar(15) NOT NULL,
  `member_gender` varchar(10) NOT NULL,
  `member_dob` date NOT NULL,
  `member_address` varchar(140) NOT NULL DEFAULT '',
  `member_state` varchar(30) NOT NULL,
  `member_city` varchar(50) NOT NULL,
  `member_postcode` char(5) NOT NULL,
  `member_points` int(6) NOT NULL DEFAULT 0,
  `member_joindate` date NOT NULL,
  `member_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `member`
--

INSERT INTO `member` (`member_id`, `member_name`, `member_email`, `member_password`, `member_phone`, `member_gender`, `member_dob`, `member_address`, `member_state`, `member_city`, `member_postcode`, `member_points`, `member_joindate`, `member_isDelete`) VALUES
(1, 'Tan Mei Ling', 'meiling@email.com', 'meiling123', '0123344556', 'Female', '2000-05-12', '', 'Selangor', 'Shah Alam', '40000', 585, '2026-01-12', 0),
(2, 'Muhammad Faiz', 'faiz@email.com', 'faiz123', '0198877665', 'Male', '1999-08-03', '', 'Kuala Lumpur', 'Kuala Lumpur', '50000', 233, '2026-02-03', 0),
(3, 'Priya Devi', 'priya@email.com', 'priya123', '0167788990', 'Female', '2001-02-21', '', 'Johor', 'Johor Bahru', '80000', 392, '2026-02-21', 0),
(4, 'Wong Jia Hui', 'jiahui@email.com', 'jiahui123', '0112233445', 'Female', '2000-03-09', '', 'Pulau Pinang', 'George Town', '10000', 188, '2026-03-09', 0);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset`
--

CREATE TABLE `password_reset` (
  `reset_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `reset_code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `attempt_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `order_member` int(11) NOT NULL,
  `order_date` datetime NOT NULL,
  `order_total` decimal(7,2) NOT NULL,
  `order_payment` varchar(20) NOT NULL,
  `order_payment_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `order_delivery` varchar(5) NOT NULL,
  `order_address` varchar(255) NOT NULL,
  `order_status` varchar(20) NOT NULL,
  `order_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `order_member`, `order_date`, `order_total`, `order_payment`, `order_payment_status`, `order_delivery`, `order_address`, `order_status`, `order_isDelete`) VALUES
(1, 1, '2026-05-20 00:00:00', 26.20, 'Online Banking', 'Pending', 'No', '', 'Delivered', 0),
(2, 2, '2026-05-21 00:00:00', 23.30, 'Credit Card', 'Pending', 'No', '', 'Preparing', 0),
(3, 3, '2026-05-22 00:00:00', 39.20, 'Cash on Delivery', 'Unpaid', 'Yes', 'No. 12, Jalan Mawar, Taman Pelangi, Johor Bahru', 'Preparing', 0),
(4, 4, '2026-05-22 00:00:00', 18.80, 'E-Wallet', 'Pending', 'No', '', 'Out for Delivery', 0),
(5, 1, '2026-05-23 00:00:00', 32.30, 'Credit Card', 'Pending', 'No', '', 'Picked Up', 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `payment_order` int(11) NOT NULL,
  `payment_reference` varchar(40) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL,
  `payment_amount` decimal(7,2) NOT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `payment_paid_at` datetime DEFAULT NULL,
  `payment_created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `item_id` int(11) NOT NULL,
  `item_order` int(11) NOT NULL,
  `item_product` char(5) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `item_price` decimal(5,2) NOT NULL,
  `item_qty` int(4) NOT NULL,
  `item_subtotal` decimal(7,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`item_id`, `item_order`, `item_product`, `item_name`, `item_price`, `item_qty`, `item_subtotal`) VALUES
(1, 1, 'P001', 'Original Recipe (1 pc)', 7.90, 2, 15.80),
(2, 1, 'P011', 'Cheezy Wedges', 6.90, 1, 6.90),
(3, 1, 'P018', 'Sprite', 3.50, 1, 3.50),
(4, 2, 'P008', 'Zinger Burger', 13.90, 1, 13.90),
(5, 2, 'P010', 'French Fries', 5.90, 1, 5.90),
(6, 2, 'P017', 'Coca-Cola', 3.50, 1, 3.50),
(7, 3, 'P005', 'Classic Burger', 10.90, 2, 21.80),
(8, 3, 'P012', 'Onion Rings', 6.50, 1, 6.50),
(9, 3, 'P019', 'Orange Juice', 5.90, 1, 5.90),
(10, 4, 'P004', 'Nuggets (6 pcs)', 9.90, 1, 9.90),
(11, 4, 'P011', 'Cheezy Wedges', 6.90, 1, 6.90),
(12, 4, 'P021', 'Mineral Water', 2.00, 1, 2.00),
(13, 5, 'P006', 'Beef Burger', 12.90, 2, 25.80),
(14, 5, 'P020', 'Iced Latte', 6.50, 1, 6.50);

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `product_id` char(5) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `product_desc` varchar(255) NOT NULL DEFAULT '',
  `product_image` varchar(255) NOT NULL DEFAULT 'image/logo.png',
  `product_category` varchar(50) NOT NULL,
  `product_price` decimal(5,2) NOT NULL,
  `product_stock` int(4) NOT NULL,
  `product_status` varchar(20) NOT NULL,
  `product_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`product_id`, `product_name`, `product_desc`, `product_image`, `product_category`, `product_price`, `product_stock`, `product_status`, `product_isDelete`) VALUES
('P001', 'Original Recipe (1 pc)', 'Our signature crispy fried chicken with the secret blend of 11 herbs and spices.', 'image/chicken-original.jpg', 'Fried Chicken', 7.90, 120, 'Active', 0),
('P002', 'Hot & Spicy (1 pc)', 'Fiery and flavourful chicken coated in a bold, spicy crust for those who love the heat.', 'image/chicken-hotspicy.jpg', 'Fried Chicken', 8.50, 100, 'Active', 0),
('P003', 'Crispy Tenders (3 pcs)', 'Tender strips of juicy chicken breast, lightly breaded and fried until perfectly crunchy.', 'image/chicken-tenders.jpg', 'Fried Chicken', 11.90, 80, 'Active', 0),
('P004', 'Nuggets (6 pcs)', 'Bite-sized golden nuggets that are great for sharing or as a tasty snack on the go.', 'image/chicken-nuggets.jpg', 'Fried Chicken', 9.90, 90, 'Active', 0),
('P005', 'Classic Burger', 'A timeless favourite with a soft bun, fresh lettuce, tomato and our special sauce.', 'image/burger-classic.jpg', 'Burger', 10.90, 75, 'Active', 0),
('P006', 'Beef Burger', 'A thick, char-grilled beef patty topped with melted cheese and crisp onions.', 'image/burger-beef.jpg', 'Burger', 12.90, 70, 'Active', 0),
('P007', 'Filet-O-Fish', 'A golden, crispy fish fillet with tartar sauce and cheese in a steamed bun.', 'image/burger-fish.jpg', 'Burger', 11.50, 60, 'Active', 0),
('P008', 'Zinger Burger', 'A spicy, crunchy chicken fillet layered with lettuce and creamy mayo.', 'image/burger-zinger.jpg', 'Burger', 13.90, 85, 'Active', 0),
('P009', 'Zinger Double Down', 'Our boldest burger with two spicy chicken fillets, bacon and cheese, no bun needed.', 'image/burger-zingerdouble.jpg', 'Burger', 15.90, 50, 'Active', 0),
('P010', 'French Fries', 'Golden, crispy fries lightly salted and served piping hot.', 'image/side-fries.jpg', 'Side Dishes', 5.90, 200, 'Active', 0),
('P011', 'Cheezy Wedges', 'Thick-cut potato wedges drizzled with warm, melty cheese sauce.', 'image/side-wedges.jpg', 'Side Dishes', 6.90, 150, 'Active', 0),
('P012', 'Onion Rings', 'Sweet onion rings in a crunchy, golden batter. Great for sharing.', 'image/side-onionrings.jpg', 'Side Dishes', 6.50, 120, 'Active', 0),
('P013', 'Corn Cup', 'Sweet buttered corn kernels served warm in a convenient cup.', 'image/side-corncup.jpg', 'Side Dishes', 4.50, 130, 'Active', 0),
('P014', 'Ice Cream Cone', 'Smooth, creamy vanilla soft-serve swirled in a crispy cone.', 'image/dessert-icecream.jpg', 'Dessert', 3.90, 110, 'Active', 0),
('P015', 'Chocolate Sundae', 'Creamy soft-serve topped with rich chocolate sauce in a cup.', 'image/dessert-sundae.jpg', 'Dessert', 5.50, 95, 'Active', 0),
('P016', 'Apple Pie', 'A warm, flaky pastry filled with sweet cinnamon apple goodness.', 'image/dessert-applepie.jpg', 'Dessert', 4.90, 0, 'Out of Stock', 0),
('P017', 'Coca-Cola', 'An ice-cold classic cola to go perfectly with any meal.', 'image/bev-coke.jpg', 'Beverage', 3.50, 300, 'Active', 0),
('P018', 'Sprite', 'A crisp, lemon-lime soda that is light and refreshing.', 'image/bev-sprite.jpg', 'Beverage', 3.50, 280, 'Active', 0),
('P019', 'Orange Juice', 'Freshly squeezed orange juice, full of natural sweetness.', 'image/bev-orangejuice.jpg', 'Beverage', 5.90, 90, 'Active', 0),
('P020', 'Iced Latte', 'Smooth espresso with chilled milk over ice for a cool pick-me-up.', 'image/bev-icedlatte.jpg', 'Beverage', 6.50, 85, 'Active', 0),
('P021', 'Mineral Water', 'Pure, refreshing bottled mineral water.', 'image/bev-water.jpg', 'Beverage', 2.00, 250, 'Active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `redemption`
--

CREATE TABLE `redemption` (
  `redeem_id` int(11) NOT NULL,
  `redeem_member` int(11) NOT NULL,
  `redeem_reward` varchar(100) NOT NULL,
  `redeem_product` char(5) NOT NULL,
  `redeem_points` int(6) NOT NULL,
  `redeem_status` varchar(10) NOT NULL DEFAULT 'Cart',
  `redeem_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `review_id` int(11) NOT NULL,
  `review_member` int(11) NOT NULL,
  `review_order` int(11) NOT NULL,
  `review_rating` int(1) NOT NULL,
  `review_comment` text NOT NULL,
  `review_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `review`
--

INSERT INTO `review` (`review_id`, `review_member`, `review_order`, `review_rating`, `review_comment`, `review_date`) VALUES
(1, 1, 1, 5, 'The food is fantastic!', '2026-05-21'),
(2, 4, 4, 4, 'Quick delivery and the burgers were still hot.', '2026-05-23');

-- --------------------------------------------------------

--
-- Table structure for table `reward`
--

CREATE TABLE `reward` (
  `reward_id` char(5) NOT NULL,
  `reward_name` varchar(100) NOT NULL,
  `reward_desc` varchar(255) NOT NULL,
  `reward_points` int(6) NOT NULL,
  `reward_product` char(5) NOT NULL,
  `reward_image` varchar(100) NOT NULL,
  `reward_status` varchar(10) NOT NULL,
  `reward_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `reward`
--

INSERT INTO `reward` (`reward_id`, `reward_name`, `reward_desc`, `reward_points`, `reward_product`, `reward_image`, `reward_status`, `reward_isDelete`) VALUES
('R001', 'Free Mineral Water', 'A refreshing bottle of mineral water, on the house.', 100, 'P021', 'bev-water.jpg', 'Active', 0),
('R002', 'Free Soft Drink', 'Any regular Coca-Cola or Sprite of your choice.', 150, 'P017', 'bev-coke.jpg', 'Active', 0),
('R003', 'Free Ice Cream Cone', 'A creamy vanilla soft-serve cone to sweeten your day.', 180, 'P014', 'dessert-icecream.jpg', 'Active', 0),
('R004', 'Free French Fries', 'A regular portion of our golden, crispy fries.', 220, 'P010', 'side-fries.jpg', 'Active', 0),
('R006', 'Free Cheezy Wedges', 'Potato wedges smothered in warm, melty cheese sauce.', 300, 'P011', 'side-wedges.jpg', 'Active', 0),
('R007', 'Free Original Recipe Chicken', 'One piece of our signature crispy fried chicken.', 380, 'P001', 'chicken-original.jpg', 'Active', 0),
('R008', 'Free Classic Burger', 'A timeless classic burger with our special sauce.', 500, 'P005', 'burger-classic.jpg', 'Active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `staff_id` char(5) NOT NULL,
  `staff_name` varchar(100) NOT NULL,
  `staff_role` varchar(20) NOT NULL,
  `staff_email` varchar(100) NOT NULL,
  `staff_phone` varchar(15) NOT NULL,
  `staff_password` varchar(50) NOT NULL,
  `staff_isDelete` int(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`staff_id`, `staff_name`, `staff_role`, `staff_email`, `staff_phone`, `staff_password`, `staff_isDelete`) VALUES
('S001', 'Andrew Tan Yong Ling', 'Manager', 'andrew@easyorder.com', '0123456789', 'admin123', 0),
('S002', 'Siti Nurhaliza', 'Cashier', 'siti@easyorder.com', '0129876543', 'admin123', 0),
('S003', 'Raj Kumar', 'Chef', 'raj@easyorder.com', '0134567890', 'admin123', 0),
('S004', 'Lim Wei Ming', 'Delivery', 'lim@easyorder.com', '0145678901', 'admin123', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uq_category_name` (`category_name`);

--
-- Indexes for table `contact_msg`
--
ALTER TABLE `contact_msg`
  ADD PRIMARY KEY (`msg_id`);

--
-- Indexes for table `member`
--
ALTER TABLE `member`
  ADD PRIMARY KEY (`member_id`),
  ADD UNIQUE KEY `uq_member_email` (`member_email`);

--
-- Indexes for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD PRIMARY KEY (`reset_id`),
  ADD KEY `idx_password_reset_member_active` (`member_id`,`used_at`,`verified_at`,`expires_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `fk_orders_member` (`order_member`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uq_payments_order` (`payment_order`),
  ADD UNIQUE KEY `uq_payments_reference` (`payment_reference`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_orderitems_order` (`item_order`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `fk_product_category` (`product_category`);

--
-- Indexes for table `redemption`
--
ALTER TABLE `redemption`
  ADD PRIMARY KEY (`redeem_id`),
  ADD KEY `fk_redeem_member` (`redeem_member`),
  ADD KEY `fk_redeem_product` (`redeem_product`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `fk_review_member` (`review_member`),
  ADD KEY `fk_review_order` (`review_order`);

--
-- Indexes for table `reward`
--
ALTER TABLE `reward`
  ADD PRIMARY KEY (`reward_id`),
  ADD KEY `fk_reward_product` (`reward_product`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `uq_staff_email` (`staff_email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_msg`
--
ALTER TABLE `contact_msg`
  MODIFY `msg_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `member`
--
ALTER TABLE `member`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `password_reset`
--
ALTER TABLE `password_reset`
  MODIFY `reset_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `redemption`
--
ALTER TABLE `redemption`
  MODIFY `redeem_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review`
--
ALTER TABLE `review`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_member` FOREIGN KEY (`order_member`) REFERENCES `member` (`member_id`) ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`payment_order`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_reset`
--
ALTER TABLE `password_reset`
  ADD CONSTRAINT `fk_password_reset_member` FOREIGN KEY (`member_id`) REFERENCES `member` (`member_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_orderitems_order` FOREIGN KEY (`item_order`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`product_category`) REFERENCES `category` (`category_name`) ON UPDATE CASCADE;

--
-- Constraints for table `redemption`
--
ALTER TABLE `redemption`
  ADD CONSTRAINT `fk_redeem_member` FOREIGN KEY (`redeem_member`) REFERENCES `member` (`member_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_redeem_product` FOREIGN KEY (`redeem_product`) REFERENCES `product` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `fk_review_member` FOREIGN KEY (`review_member`) REFERENCES `member` (`member_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_review_order` FOREIGN KEY (`review_order`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `reward`
--
ALTER TABLE `reward`
  ADD CONSTRAINT `fk_reward_product` FOREIGN KEY (`reward_product`) REFERENCES `product` (`product_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
