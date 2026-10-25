<?php
include("dataconnection.php");
require_once("customer_navigation.php");
require_once("email_helpers.php");

if(isset($_SESSION["customer_id"]) && $_SERVER["REQUEST_METHOD"]!=="POST")
{
	header("location:profile.php");
	exit();
}

$states = array("Johor","Kedah","Kelantan","Melaka","Negeri Sembilan","Pahang","Perak","Perlis","Pulau Pinang","Sabah","Sarawak","Selangor","Terengganu","Kuala Lumpur","Labuan","Putrajaya");

/*
 * City choices adapted from https://github.com/AsyrafHussin/malaysia-postcodes
 * Dataset revision: 842ca633a72571d8b1cc7e445eefd536cb773f19
 * Postal city/locality names are kept alongside major city names.
 * George Town: https://tempahan.mbpp.gov.my/
 * Seberang Perai: https://www.mbsp.gov.my/index.php/en/hubungi-kami/lokasi
 *
 * MIT License
 *
 * Copyright (c) 2020-2025 Asyraf Hussin
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
$signup_cities_by_state = array(
	"Johor" => array(
		"Ayer Baloi", "Ayer Hitam", "Ayer Tawar 2", "Bandar Penawar", "Bandar Tenggara", "Batu Anam",
		"Batu Pahat", "Bekok", "Benut", "Bukit Gambir", "Bukit Pasir", "Chaah", "Endau", "Gelang Patah",
		"Gerisek", "Gugusan Taib Andak", "Ibrahim International Business District", "Iskandar Puteri", "Jementah",
		"Johor Bahru", "Kahang", "Kluang", "Kota Tinggi", "Kukup", "Kulai", "Labis", "Layang-Layang", "Masai",
		"Mersing", "Muar", "Pagoh", "Paloh", "Panchor", "Parit Jawa", "Parit Raja", "Parit Sulong",
		"Pasir Gudang", "Pekan Nenas", "Pengerang", "Pontian", "Rengam", "Rengit", "Segamat", "Semerah", "Senai",
		"Senggarang", "Seri Gading", "Seri Medan", "Simpang Rengam", "Sungai Mati", "Tangkak", "Tioman",
		"Ulu Tiram", "Yong Peng"
	),
	"Kedah" => array(
		"Alor Setar", "Ayer Hitam", "Baling", "Bandar Baharu", "Bandar Bahru", "Bedong", "Bukit Kayu Hitam",
		"Changloon", "Gurun", "Jeniang", "Jitra", "Karangan", "Kepala Batas", "Kodiang", "Kota Kuala Muda",
		"Kota Sarang Semut", "Kuala Kedah", "Kuala Ketil", "Kuala Nerang", "Kuala Pegang", "Kulim", "Kupang",
		"Langgar", "Langkawi", "Lunas", "Merbok", "Padang Serai", "Pendang", "Pokok Sena", "Serdang", "Sik",
		"Simpang Empat", "Sungai Petani", "Yan"
	),
	"Kelantan" => array(
		"Ayer Lanas", "Bachok", "Cherang Ruku", "Dabong", "Gua Musang", "Jeli", "Kem Desa Pahlawan", "Ketereh",
		"Kota Bharu", "Kuala Balah", "Kuala Krai", "Machang", "Melor", "Pasir Mas", "Pasir Puteh",
		"Pulai Chondong", "Rantau Panjang", "Selising", "Tanah Merah", "Temangan", "Tumpat", "Wakaf Bharu"
	),
	"Melaka" => array(
		"Alor Gajah", "Asahan", "Ayer Keroh", "Bemban", "Durian Tunggal", "Jasin", "Kem Trendak",
		"Kuala Sungai Baru", "Lubok China", "Masjid Tanah", "Melaka", "Merlimau", "Selandar", "Sungai Rambai",
		"Sungai Udang", "Tanjong Kling"
	),
	"Negeri Sembilan" => array(
		"Bahau", "Bandar Baru Enstek", "Bandar Seri Jempol", "Batu Kikir", "Gemas", "Gemencheh", "Johol", "Kota",
		"Kuala Klawang", "Kuala Pilah", "Labu", "Linggi", "Mantin", "Nilai", "Port Dickson",
		"Pusat Bandar Palong", "Rantau", "Rembau", "Rompin", "Seremban", "Si Rusa", "Simpang Durian",
		"Simpang Pertang", "Tampin", "Tanjong Ipoh"
	),
	"Pahang" => array(
		"Balok", "Bandar Pusat Jengka", "Bandar Tun Abdul Razak", "Benta", "Bentong", "Brinchang", "Bukit Fraser",
		"Bukit Goh", "Chenor", "Chini", "Damak", "Dong", "Gambang", "Genting Highlands", "Jaya Gading",
		"Jerantut", "Karak", "Kemayan", "Kuala Krau", "Kuala Lipis", "Kuala Rompin", "Kuantan", "Lanchang",
		"Lurah Bilut", "Maran", "Mentakab", "Muadzam Shah", "Padang Tengku", "Pekan", "Raub", "Ringlet", "Sega",
		"Sungai Koyan", "Sungai Lembing", "Sungai Ruan", "Tanah Rata", "Temerloh", "Triang"
	),
	"Perak" => array(
		"Ayer Tawar", "Bagan Datoh", "Bagan Serai", "Bandar Seri Iskandar", "Batu Gajah", "Batu Kurau",
		"Behrang Stesen", "Bidor", "Bota", "Bruas", "Changkat Jering", "Changkat Keruing", "Chemor",
		"Chenderiang", "Chenderong Balai", "Chikus", "Enggor", "Gerik", "Gopeng", "Hutan Melintang", "Intan",
		"Ipoh", "Jeram", "Kampar", "Kampung Gajah", "Kampung Kepayang", "Kamunting", "Kuala Kangsar",
		"Kuala Kurau", "Kuala Sepetang", "Lambor Kanan", "Langkap", "Lenggong", "Lumut", "Malim Nawar", "Manong",
		"Matang", "Padang Rengas", "Pangkor", "Pantai Remis", "Parit", "Parit Buntar", "Pengkalan Hulu", "Pusing",
		"Rantau Panjang", "Sauk", "Selama", "Selekoh", "Seri Manjung", "Simpang", "Simpang Ampat Semanggol",
		"Sitiawan", "Slim River", "Sungai Siput", "Sungai Sumun", "Sungkai", "Taiping", "Tanjong Malim",
		"Tanjong Piandang", "Tanjong Rambutan", "Tanjong Tualang", "Tapah", "Tapah Road", "Teluk Intan", "Temoh",
		"TLDM Lumut", "Trolak", "Trong", "Tronoh", "Ulu Bernam", "Ulu Kinta"
	),
	"Perlis" => array(
		"Arau", "Kaki Bukit", "Kangar", "Kuala Perlis", "Padang Besar", "Simpang Ampat"
	),
	"Pulau Pinang" => array(
		"Ayer Itam", "Balik Pulau", "Batu Ferringhi", "Batu Maung", "Bayan Lepas", "Bukit Mertajam",
		"Butterworth", "Gelugor", "George Town", "Jelutong", "Kepala Batas", "Kubang Semang", "Nibong Tebal",
		"Penaga", "Penang Hill", "Perai", "Permatang Pauh", "Pulau Pinang", "Seberang Perai", "Simpang Ampat",
		"Sungai Jawi", "Tanjong Bungah", "Tasek Gelugor", "Usm Pulau Pinang"
	),
	"Sabah" => array(
		"Beaufort", "Beluran", "Beverly", "Bongawan", "Inanam", "Keningau", "Kota Belud", "Kota Kinabalu",
		"Kota Kinabatangan", "Kota Marudu", "Kuala Penyu", "Kudat", "Kunak", "Lahad Datu", "Likas", "Membakut",
		"Menumbok", "Nabawan", "Pamol", "Papar", "Penampang", "Putatan", "Ranau", "Sandakan", "Semporna",
		"Sipitang", "Tambunan", "Tamparuli", "Tanjung Aru", "Tawau", "Tenghilan", "Tenom", "Tuaran"
	),
	"Sarawak" => array(
		"Asajaya", "Balingian", "Baram", "Bau", "Bekenu", "Belaga", "Belawai", "Betong", "Bintangor", "Bintulu",
		"Dalat", "Daro", "Debak", "Engkilili", "Julau", "Kabong", "Kanowit", "Kapit", "Kota Samarahan", "Kuching",
		"Lawas", "Limbang", "Lingga", "Long Lama", "Lubok Antu", "Lundu", "Lutong", "Matu", "Miri", "Mukah",
		"Nanga Medamit", "Niah", "Pusa", "Pusat Mel Miri", "Roban", "Saratok", "Sarikei", "Sebauh", "Sebuyau",
		"Serian", "Sibu", "Sibu Jaya", "Siburan", "Simunjan", "Song", "Spaoh", "Sri Aman", "Sundar", "Tatau"
	),
	"Selangor" => array(
		"Ampang", "Bandar Baru Bangi", "Bandar Puncak Alam", "Bangi", "Banting", "Batang Berjuntai",
		"Batang Kali", "Batu Caves", "Beranang", "Bukit Rotan", "Cheras", "Cyberjaya", "Dengkil", "Hulu Langat",
		"Jenjarom", "Jeram", "Kajang", "Kapar", "Kerling", "Klang", "KLIA", "Kuala Kubu Baru", "Kuala Selangor",
		"Pelabuhan Klang", "Petaling Jaya", "Puchong", "Pulau Carey", "Pulau Indah", "Pulau Ketam", "Rasa",
		"Rawang", "Sabak Bernam", "Sekinchan", "Semenyih", "Sepang", "Serdang", "Serendah", "Seri Kembangan",
		"Shah Alam", "Subang Airport", "Subang Jaya", "Sungai Ayer Tawar", "Sungai Besar", "Sungai Buloh",
		"Sungai Pelek", "Tanjong Karang", "Tanjong Sepat", "Telok Panglima Garang"
	),
	"Terengganu" => array(
		"Ajil", "Al Muktatfi Billah Shah", "Ayer Puteh", "Bukit Besi", "Bukit Payong", "Ceneh", "Chalok", "Cukai",
		"Dungun", "Jerteh", "Kampung Raja", "Kemasek", "Kerteh", "Ketengah Jaya", "Kijal", "Kuala Berang",
		"Kuala Besut", "Kuala Terengganu", "Marang", "Paka", "Permaisuri", "Sungai Tong"
	),
	"Kuala Lumpur" => array(
		"Kuala Lumpur", "Setapak"
	),
	"Labuan" => array(
		"Labuan"
	),
	"Putrajaya" => array(
		"Putrajaya"
	)
);

/*
 * Calling codes and national prefixes adapted from https://github.com/jackocnr/intl-tel-input
 * Dataset revision: 5a5aad0acc732929ae54741f2ac5dc63ec51af2b
 * Values: country/region name, calling code, national prefix.
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2016 Jack O'Connor
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
$signup_phone_countries = array(
	"AF" => array("Afghanistan", "93", "0"),
	"AL" => array("Albania", "355", "0"),
	"DZ" => array("Algeria", "213", "0"),
	"AS" => array("American Samoa", "1", "1"),
	"AD" => array("Andorra", "376", ""),
	"AO" => array("Angola", "244", ""),
	"AI" => array("Anguilla", "1", "1"),
	"AG" => array("Antigua and Barbuda", "1", "1"),
	"AR" => array("Argentina", "54", "0"),
	"AM" => array("Armenia", "374", "0"),
	"AW" => array("Aruba", "297", ""),
	"AC" => array("Ascension Island", "247", ""),
	"AU" => array("Australia", "61", "0"),
	"AT" => array("Austria", "43", "0"),
	"AZ" => array("Azerbaijan", "994", "0"),
	"BS" => array("Bahamas", "1", "1"),
	"BH" => array("Bahrain", "973", ""),
	"BD" => array("Bangladesh", "880", "0"),
	"BB" => array("Barbados", "1", "1"),
	"BY" => array("Belarus", "375", "8"),
	"BE" => array("Belgium", "32", "0"),
	"BZ" => array("Belize", "501", ""),
	"BJ" => array("Benin", "229", ""),
	"BM" => array("Bermuda", "1", "1"),
	"BT" => array("Bhutan", "975", ""),
	"BO" => array("Bolivia", "591", "0"),
	"BA" => array("Bosnia and Herzegovina", "387", "0"),
	"BW" => array("Botswana", "267", ""),
	"BR" => array("Brazil", "55", "0"),
	"IO" => array("British Indian Ocean Territory", "246", ""),
	"VG" => array("British Virgin Islands", "1", "1"),
	"BN" => array("Brunei", "673", ""),
	"BG" => array("Bulgaria", "359", "0"),
	"BF" => array("Burkina Faso", "226", ""),
	"BI" => array("Burundi", "257", ""),
	"KH" => array("Cambodia", "855", "0"),
	"CM" => array("Cameroon", "237", ""),
	"CA" => array("Canada", "1", "1"),
	"CV" => array("Cape Verde", "238", ""),
	"BQ" => array("Caribbean Netherlands", "599", ""),
	"KY" => array("Cayman Islands", "1", "1"),
	"CF" => array("Central African Republic", "236", ""),
	"TD" => array("Chad", "235", ""),
	"CL" => array("Chile", "56", ""),
	"CN" => array("China", "86", "0"),
	"CX" => array("Christmas Island", "61", "0"),
	"CC" => array("Cocos (Keeling) Islands", "61", "0"),
	"CO" => array("Colombia", "57", "0"),
	"KM" => array("Comoros", "269", ""),
	"CG" => array("Congo (Brazzaville)", "242", ""),
	"CD" => array("Congo (Kinshasa)", "243", "0"),
	"CK" => array("Cook Islands", "682", ""),
	"CR" => array("Costa Rica", "506", ""),
	"HR" => array("Croatia", "385", "0"),
	"CU" => array("Cuba", "53", "0"),
	"CW" => array("Curaçao", "599", ""),
	"CY" => array("Cyprus", "357", ""),
	"CZ" => array("Czech Republic", "420", ""),
	"CI" => array("Côte d'Ivoire", "225", ""),
	"DK" => array("Denmark", "45", ""),
	"DJ" => array("Djibouti", "253", ""),
	"DM" => array("Dominica", "1", "1"),
	"DO" => array("Dominican Republic", "1", "1"),
	"EC" => array("Ecuador", "593", "0"),
	"EG" => array("Egypt", "20", "0"),
	"SV" => array("El Salvador", "503", ""),
	"GQ" => array("Equatorial Guinea", "240", ""),
	"ER" => array("Eritrea", "291", "0"),
	"EE" => array("Estonia", "372", ""),
	"SZ" => array("Eswatini", "268", ""),
	"ET" => array("Ethiopia", "251", "0"),
	"FK" => array("Falkland Islands (Malvinas)", "500", ""),
	"FO" => array("Faroe Islands", "298", ""),
	"FJ" => array("Fiji", "679", ""),
	"FI" => array("Finland", "358", "0"),
	"FR" => array("France", "33", "0"),
	"GF" => array("French Guiana", "594", "0"),
	"PF" => array("French Polynesia", "689", ""),
	"GA" => array("Gabon", "241", ""),
	"GM" => array("Gambia", "220", ""),
	"GE" => array("Georgia", "995", "0"),
	"DE" => array("Germany", "49", "0"),
	"GH" => array("Ghana", "233", "0"),
	"GI" => array("Gibraltar", "350", ""),
	"GR" => array("Greece", "30", ""),
	"GL" => array("Greenland", "299", ""),
	"GD" => array("Grenada", "1", "1"),
	"GP" => array("Guadeloupe", "590", "0"),
	"GU" => array("Guam", "1", "1"),
	"GT" => array("Guatemala", "502", ""),
	"GG" => array("Guernsey", "44", "0"),
	"GN" => array("Guinea", "224", ""),
	"GW" => array("Guinea-Bissau", "245", ""),
	"GY" => array("Guyana", "592", ""),
	"HT" => array("Haiti", "509", ""),
	"HN" => array("Honduras", "504", ""),
	"HK" => array("Hong Kong SAR China", "852", ""),
	"HU" => array("Hungary", "36", "06"),
	"IS" => array("Iceland", "354", ""),
	"IN" => array("India", "91", "0"),
	"ID" => array("Indonesia", "62", "0"),
	"IR" => array("Iran", "98", "0"),
	"IQ" => array("Iraq", "964", "0"),
	"IE" => array("Ireland", "353", "0"),
	"IM" => array("Isle of Man", "44", "0"),
	"IL" => array("Israel", "972", "0"),
	"IT" => array("Italy", "39", ""),
	"JM" => array("Jamaica", "1", "1"),
	"JP" => array("Japan", "81", "0"),
	"JE" => array("Jersey", "44", "0"),
	"JO" => array("Jordan", "962", "0"),
	"KZ" => array("Kazakhstan", "7", "8"),
	"KE" => array("Kenya", "254", "0"),
	"KI" => array("Kiribati", "686", "0"),
	"XK" => array("Kosovo", "383", "0"),
	"KW" => array("Kuwait", "965", ""),
	"KG" => array("Kyrgyzstan", "996", "0"),
	"LA" => array("Laos", "856", "0"),
	"LV" => array("Latvia", "371", ""),
	"LB" => array("Lebanon", "961", "0"),
	"LS" => array("Lesotho", "266", ""),
	"LR" => array("Liberia", "231", "0"),
	"LY" => array("Libya", "218", "0"),
	"LI" => array("Liechtenstein", "423", "0"),
	"LT" => array("Lithuania", "370", "0"),
	"LU" => array("Luxembourg", "352", ""),
	"MO" => array("Macao SAR China", "853", ""),
	"MG" => array("Madagascar", "261", "0"),
	"MW" => array("Malawi", "265", "0"),
	"MY" => array("Malaysia", "60", "0"),
	"MV" => array("Maldives", "960", ""),
	"ML" => array("Mali", "223", ""),
	"MT" => array("Malta", "356", ""),
	"MH" => array("Marshall Islands", "692", "1"),
	"MQ" => array("Martinique", "596", "0"),
	"MR" => array("Mauritania", "222", ""),
	"MU" => array("Mauritius", "230", ""),
	"YT" => array("Mayotte", "262", "0"),
	"MX" => array("Mexico", "52", ""),
	"FM" => array("Micronesia", "691", ""),
	"MD" => array("Moldova", "373", "0"),
	"MC" => array("Monaco", "377", "0"),
	"MN" => array("Mongolia", "976", "0"),
	"ME" => array("Montenegro", "382", "0"),
	"MS" => array("Montserrat", "1", "1"),
	"MA" => array("Morocco", "212", "0"),
	"MZ" => array("Mozambique", "258", ""),
	"MM" => array("Myanmar (Burma)", "95", "0"),
	"NA" => array("Namibia", "264", "0"),
	"NR" => array("Nauru", "674", ""),
	"NP" => array("Nepal", "977", "0"),
	"NL" => array("Netherlands", "31", "0"),
	"NC" => array("New Caledonia", "687", ""),
	"NZ" => array("New Zealand", "64", "0"),
	"NI" => array("Nicaragua", "505", ""),
	"NE" => array("Niger", "227", ""),
	"NG" => array("Nigeria", "234", "0"),
	"NU" => array("Niue", "683", ""),
	"NF" => array("Norfolk Island", "672", ""),
	"KP" => array("North Korea", "850", "0"),
	"MK" => array("North Macedonia", "389", "0"),
	"MP" => array("Northern Mariana Islands", "1", "1"),
	"NO" => array("Norway", "47", ""),
	"OM" => array("Oman", "968", ""),
	"PK" => array("Pakistan", "92", "0"),
	"PW" => array("Palau", "680", ""),
	"PS" => array("Palestinian Territories", "970", "0"),
	"PA" => array("Panama", "507", ""),
	"PG" => array("Papua New Guinea", "675", ""),
	"PY" => array("Paraguay", "595", "0"),
	"PE" => array("Peru", "51", "0"),
	"PH" => array("Philippines", "63", "0"),
	"PL" => array("Poland", "48", ""),
	"PT" => array("Portugal", "351", ""),
	"PR" => array("Puerto Rico", "1", "1"),
	"QA" => array("Qatar", "974", ""),
	"RO" => array("Romania", "40", "0"),
	"RU" => array("Russia", "7", "8"),
	"RW" => array("Rwanda", "250", "0"),
	"RE" => array("Réunion", "262", "0"),
	"WS" => array("Samoa", "685", ""),
	"SM" => array("San Marino", "378", ""),
	"SA" => array("Saudi Arabia", "966", "0"),
	"SN" => array("Senegal", "221", ""),
	"RS" => array("Serbia", "381", "0"),
	"SC" => array("Seychelles", "248", ""),
	"SL" => array("Sierra Leone", "232", "0"),
	"SG" => array("Singapore", "65", ""),
	"SX" => array("Sint Maarten", "1", "1"),
	"SK" => array("Slovakia", "421", "0"),
	"SI" => array("Slovenia", "386", "0"),
	"SB" => array("Solomon Islands", "677", ""),
	"SO" => array("Somalia", "252", "0"),
	"ZA" => array("South Africa", "27", "0"),
	"KR" => array("South Korea", "82", "0"),
	"SS" => array("South Sudan", "211", "0"),
	"ES" => array("Spain", "34", ""),
	"LK" => array("Sri Lanka", "94", "0"),
	"BL" => array("St. Barthélemy", "590", "0"),
	"SH" => array("St. Helena", "290", ""),
	"KN" => array("St. Kitts & Nevis", "1", "1"),
	"LC" => array("St. Lucia", "1", "1"),
	"MF" => array("St. Martin", "590", "0"),
	"PM" => array("St. Pierre & Miquelon", "508", "0"),
	"VC" => array("St. Vincent & Grenadines", "1", "1"),
	"SD" => array("Sudan", "249", "0"),
	"SR" => array("Suriname", "597", ""),
	"SJ" => array("Svalbard & Jan Mayen", "47", ""),
	"SE" => array("Sweden", "46", "0"),
	"CH" => array("Switzerland", "41", "0"),
	"SY" => array("Syria", "963", "0"),
	"ST" => array("São Tomé & Príncipe", "239", ""),
	"TW" => array("Taiwan", "886", "0"),
	"TJ" => array("Tajikistan", "992", ""),
	"TZ" => array("Tanzania", "255", "0"),
	"TH" => array("Thailand", "66", "0"),
	"TL" => array("Timor-Leste", "670", ""),
	"TG" => array("Togo", "228", ""),
	"TK" => array("Tokelau", "690", ""),
	"TO" => array("Tonga", "676", ""),
	"TT" => array("Trinidad & Tobago", "1", "1"),
	"TN" => array("Tunisia", "216", ""),
	"TR" => array("Turkey", "90", "0"),
	"TM" => array("Turkmenistan", "993", "8"),
	"TC" => array("Turks & Caicos Islands", "1", "1"),
	"TV" => array("Tuvalu", "688", ""),
	"VI" => array("U.S. Virgin Islands", "1", "1"),
	"UG" => array("Uganda", "256", "0"),
	"UA" => array("Ukraine", "380", "0"),
	"AE" => array("United Arab Emirates", "971", "0"),
	"GB" => array("United Kingdom", "44", "0"),
	"US" => array("United States", "1", "1"),
	"UY" => array("Uruguay", "598", "0"),
	"UZ" => array("Uzbekistan", "998", ""),
	"VU" => array("Vanuatu", "678", ""),
	"VA" => array("Vatican City", "39", ""),
	"VE" => array("Venezuela", "58", "0"),
	"VN" => array("Vietnam", "84", "0"),
	"WF" => array("Wallis & Futuna", "681", ""),
	"EH" => array("Western Sahara", "212", "0"),
	"YE" => array("Yemen", "967", "0"),
	"ZM" => array("Zambia", "260", "0"),
	"ZW" => array("Zimbabwe", "263", "0"),
	"AX" => array("Åland Islands", "358", "0")
);

$register_errors = array();
$register_values = array(
	"name" => "",
	"email" => "",
	"confirm_email" => "",
	"phone" => "",
	"phone_country" => "MY",
	"gender" => "",
	"dob" => "",
	"state" => "",
	"city" => "",
	"postcode" => ""
);

function register_h($value)
{
	return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");
}

function signup_international_phone($country,$value)
{
	global $signup_phone_countries;
	if(!isset($signup_phone_countries[$country]) || strlen($value)>32) return "";
	$phone = preg_replace('/[\s().-]+/',"",trim($value));
	if(!preg_match('/^\+?[0-9]+$/D',$phone)) return "";
	$calling_code = $signup_phone_countries[$country][1];
	$national_prefix = $signup_phone_countries[$country][2];
	$international = substr($phone,0,1)==="+" || substr($phone,0,2)==="00";
	if($international)
	{
		$digits = substr($phone,0,1)==="+" ? substr($phone,1) : substr($phone,2);
		if(substr($digits,0,strlen($calling_code))!==$calling_code) return "";
		$national = substr($digits,strlen($calling_code));
	}
	else
	{
		$national = $phone;
		if($national_prefix!=="" && substr($national,0,strlen($national_prefix))===$national_prefix
			&& ($national_prefix!=="1" || strlen($national)===11))
		{
			$national = substr($national,strlen($national_prefix));
		}
	}
	$number = $calling_code.$national;
	// Store the international digits within the existing VARCHAR(15) field.
	return preg_match('/^[0-9]{4,}$/D',$national) && preg_match('/^[1-9][0-9]{6,14}$/D',$number) ? $number : "";
}

function signup_account_conflicts($connect,$email,$phone,$country)
{
	$errors = array();
	try
	{
		if($email!=="")
		{
			$stmt = mysqli_prepare($connect,"SELECT customer_id FROM customer WHERE customer_email=? LIMIT 1");
			if(!$stmt) throw new RuntimeException("Account check unavailable");
			mysqli_stmt_bind_param($stmt,"s",$email);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			if(mysqli_stmt_num_rows($stmt)>0) $errors["email"] = "This email is already registered. Log in or use another email.";
			mysqli_stmt_close($stmt);
		}
		if($phone!=="")
		{
			$variants = array($phone,"00".$phone);
			// Earlier customer records used the Malaysian national format.
			if($country==="MY" && substr($phone,0,2)==="60") $variants[] = "0".substr($phone,2);
			$placeholders = implode(",",array_fill(0,count($variants),"?"));
			$sql = "SELECT customer_id FROM customer WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(customer_phone,'+',''),' ',''),'-',''),'(',''),')',''),'.','') IN (".$placeholders.") LIMIT 1";
			$stmt = mysqli_prepare($connect,$sql);
			if(!$stmt) throw new RuntimeException("Account check unavailable");
			mysqli_stmt_bind_param($stmt,str_repeat("s",count($variants)),...$variants);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			if(mysqli_stmt_num_rows($stmt)>0) $errors["phone"] = "This phone number is already registered. Log in or use another number.";
			mysqli_stmt_close($stmt);
		}
	}
	catch(Throwable $error)
	{
		$errors["general"] = "We couldn't check your account details. Please try again.";
	}
	return $errors;
}

function signup_phone_lock($connect,$phone,$release=false)
{
	try
	{
		$operation = $release ? "RELEASE_LOCK" : "GET_LOCK";
		$timeout = $release ? "" : ",5";
		$sql = "SELECT ".$operation."(CONCAT('eo_signup_',LEFT(SHA2(CONCAT(DATABASE(),':',?),256),48))".$timeout.")";
		$stmt = mysqli_prepare($connect,$sql);
		if(!$stmt) return false;
		mysqli_stmt_bind_param($stmt,"s",$phone);
		mysqli_stmt_execute($stmt);
		$result = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
		mysqli_stmt_close($stmt);
		return isset($result[0]) && (int)$result[0]===1;
	}
	catch(Throwable $error)
	{
		return false;
	}
}

if(!isset($_SESSION["register_csrf"]))
{
	$_SESSION["register_csrf"] = bin2hex(random_bytes(32));
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["signup_check"]))
{
	header("Content-Type: application/json; charset=UTF-8");
	header("Cache-Control: no-store");
	$errors = array();
	$token = (string)($_POST["register_csrf"] ?? "");
	if($token==="" || !hash_equals($_SESSION["register_csrf"],$token))
	{
		http_response_code(403);
		$errors["general"] = "Your registration form has expired. Please refresh the page and try again.";
	}
	else
	{
		$email = strtolower(trim((string)($_POST["cust_email"] ?? "")));
		$country = strtoupper(trim((string)($_POST["phone_country"] ?? "MY")));
		$phone = signup_international_phone($country,trim((string)($_POST["cust_phone"] ?? "")));
		if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100) $errors["email"] = "Enter a valid email address.";
		if($phone==="") $errors["phone"] = "Select a country and enter a valid phone number.";
		if(count($errors)===0) $errors = signup_account_conflicts($connect,$email,$phone,$country);
		if(isset($errors["general"])) http_response_code(503);
	}
	echo json_encode(array("available"=>count($errors)===0,"errors"=>(object)$errors));
	exit();
}

if($_SERVER["REQUEST_METHOD"]==="POST" && isset($_POST["signupbtn"]))
{
	$register_values = array(
		"name" => trim((string)($_POST["cust_name"] ?? "")),
		"email" => strtolower(trim((string)($_POST["cust_email"] ?? ""))),
		"confirm_email" => strtolower(trim((string)($_POST["cust_confirm_email"] ?? ""))),
		"phone" => trim((string)($_POST["cust_phone"] ?? "")),
		"phone_country" => strtoupper(trim((string)($_POST["phone_country"] ?? "MY"))),
		"gender" => trim((string)($_POST["gender"] ?? "")),
		"dob" => trim((string)($_POST["cust_dob"] ?? "")),
		"state" => trim((string)($_POST["state"] ?? "")),
		"city" => trim((string)($_POST["cust_city"] ?? "")),
		"postcode" => trim((string)($_POST["cust_postcode"] ?? ""))
	);
	$password = (string)($_POST["cust_password"] ?? "");
	$confirm_password = (string)($_POST["cust_confirm_password"] ?? "");
	$submitted_token = (string)($_POST["register_csrf"] ?? "");

	if($submitted_token==="" || !hash_equals($_SESSION["register_csrf"],$submitted_token))
	{
		$register_errors["general"] = "Your registration form has expired. Please refresh the page and try again.";
	}
	if(strlen($register_values["name"])<2 || strlen($register_values["name"])>100)
	{
		$register_errors["name"] = "Enter your full name using 2 to 100 characters.";
	}
	if(!filter_var($register_values["email"],FILTER_VALIDATE_EMAIL) || strlen($register_values["email"])>100)
	{
		$register_errors["email"] = "Enter a valid email address.";
	}
	if($register_values["confirm_email"]==="" || !hash_equals($register_values["email"],$register_values["confirm_email"]))
	{
		$register_errors["confirm_email"] = "Email addresses do not match.";
	}
	if(strlen($password)<8 || strlen($password)>72)
	{
		$register_errors["password"] = "Use a password containing 8 to 72 characters.";
	}
	if($confirm_password==="" || !hash_equals($password,$confirm_password))
	{
		$register_errors["confirm_password"] = "Passwords do not match.";
	}
	$international_phone = signup_international_phone($register_values["phone_country"],$register_values["phone"]);
	if($international_phone==="")
	{
		$register_errors["phone"] = "Select a country and enter a valid phone number.";
	}
	if(!in_array($register_values["gender"],array("Male","Female"),true))
	{
		$register_errors["gender"] = "Select your gender.";
	}

	$dob = DateTime::createFromFormat("Y-m-d",$register_values["dob"]);
	$dob_valid = $dob && $dob->format("Y-m-d")===$register_values["dob"] && $register_values["dob"]>="1900-01-01" && $register_values["dob"]<=date("Y-m-d");
	if(!$dob_valid)
	{
		$register_errors["dob"] = "Enter a valid date of birth.";
	}
	if(!in_array($register_values["state"],$states,true))
	{
		$register_errors["state"] = "Select your state.";
	}
	if(!in_array($register_values["city"],$signup_cities_by_state[$register_values["state"]] ?? array(),true))
	{
		$register_errors["city"] = "Select a city in your selected state.";
	}
	if(!preg_match("/^\d{5}$/",$register_values["postcode"]))
	{
		$register_errors["postcode"] = "Enter a valid 5-digit postcode.";
	}

	if(!isset($register_errors["general"]))
	{
		$account_errors = signup_account_conflicts($connect,isset($register_errors["email"]) ? "" : $register_values["email"],$international_phone,$register_values["phone_country"]);
		$register_errors = array_merge($register_errors,$account_errors);
	}

	if(count($register_errors)===0)
	{
		$join_date = date("Y-m-d");
		$inserted = false;
		if(!signup_phone_lock($connect,$international_phone))
		{
			$register_errors["general"] = "We couldn't finish checking your phone number. Please try again.";
		}
		else
		{
			try
			{
				$register_errors = signup_account_conflicts($connect,$register_values["email"],$international_phone,$register_values["phone_country"]);
				if(count($register_errors)===0)
				{
					$stmt = mysqli_prepare($connect,"INSERT INTO customer(customer_name,customer_email,customer_password,customer_phone,customer_gender,customer_dob,customer_state,customer_city,customer_postcode,customer_joindate) VALUES(?,?,?,?,?,?,?,?,?,?)");
					if($stmt)
					{
						mysqli_stmt_bind_param(
							$stmt,
							"ssssssssss",
							$register_values["name"],
							$register_values["email"],
							$password,
							$international_phone,
							$register_values["gender"],
							$register_values["dob"],
							$register_values["state"],
							$register_values["city"],
							$register_values["postcode"],
							$join_date
						);
						try
						{
							$inserted = mysqli_stmt_execute($stmt);
						}
						catch(mysqli_sql_exception $exception)
						{
							if((int)$exception->getCode()===1062)
							{
								$register_errors["email"] = "This email is already registered. Log in or use another email.";
							}
							else
							{
								$register_errors["general"] = "We could not create your account right now. Please try again.";
							}
						}
						mysqli_stmt_close($stmt);
					}
				}
			}
			finally
			{
				signup_phone_lock($connect,$international_phone,true);
			}
		}

		if($inserted)
		{
			$welcome_sent = easyorder_send_welcome_email($register_values["email"],$register_values["name"],"Customer");
			if(!$welcome_sent)
			{
				error_log("EasyOrder customer welcome email could not be sent.");
			}
			unset($_SESSION["register_csrf"]);
			$_SESSION["registration_success"] = $welcome_sent
				? "Registration successful. A welcome email has been sent and you can now log in."
				: "Registration successful. Email delivery is unavailable, but you can still log in now.";
			header("location:login.php");
			exit();
		}
		else if(count($register_errors)===0)
		{
			$register_errors["general"] = "We could not create your account right now. Please try again.";
		}
	}
}

// Select the feedback panel; passwords are not rendered into server HTML.
$signup_account_keys = array("name","phone","email","confirm_email","password","confirm_password");
$signup_personal_keys = array("gender","dob","state","city","postcode");
$signup_account_errors = count(array_intersect($signup_account_keys,array_keys($register_errors)))>0;
$signup_personal_errors = count(array_intersect($signup_personal_keys,array_keys($register_errors)))>0;
$signup_start_step = !$signup_account_errors && $signup_personal_errors && !isset($register_errors["general"]) ? 2 : 1;
$signup_password_reminder = $_SERVER["REQUEST_METHOD"]==="POST" && count($register_errors)>0;
function signup_field_aria($key,$id,$description="")
{
    global $register_errors;
    if(isset($register_errors[$key])) $description = trim($description." ".$id."-error");
    return (isset($register_errors[$key]) ? ' aria-invalid="true"' : "")
        .($description!=="" ? ' aria-describedby="'.register_h($description).'"' : "");
}
function signup_field_error($key,$id)
{
    global $register_errors;
    if(isset($register_errors[$key])) echo '<small class="eo-server-error" id="'.register_h($id).'-error">'.register_h($register_errors[$key]).'</small>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<link rel="icon" type="image/jpeg" href="image/logo.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Your Account</title>
<link rel="stylesheet" href="style.css?v=20261024-auth1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600&amp;display=swap">
<script src="customer_navigation.js?v=20261025-account-check1" defer></script>
<script src="password_ux.js?v=20260926-1" defer></script>
<style data-eo-auth-style="signup">

/* ======================================
   EASYORDER SIGN UP V11
   DESKTOP REGISTRATION DESIGN
====================================== */

#main.eo-signup {
    --eo-cream: #FFF8F0;
    --eo-paper: #FFFCF9;
    --eo-brown: #39292B;
    --eo-ink: #371C23;
    --eo-muted: #746D69;
    --eo-red: #C8102E;
    --eo-input: #FAF6F3;
    --eo-line: #E9DFDB;
    --eo-peach: #FFD09A;
}

/* ======================================
   MAIN CONTAINER
====================================== */

#main.eo-signup {
    box-sizing: border-box;
    width: min(1380px, calc(100% - 80px));
    max-width: none;
    padding: 0;
    margin: 48px auto 64px;
    font-family: "Segoe UI", Arial, sans-serif;
}

.eo-signup * {
    box-sizing: border-box;
    font-family: "Segoe UI", Arial, sans-serif;
}

/* ======================================
   MAIN CARD
====================================== */

.eo-auth-card {
    display: grid;

    /* FORM LEFT / PHOTO RIGHT */
    grid-template-columns: 1.14fr 0.86fr;

    min-height: 824px;

    background: var(--eo-paper);

    border: 1px solid rgba(80,36,46,.08);
    border-radius: 28px;
    overflow: hidden;

    box-shadow:
        0 27px 75px rgba(65,29,37,.11),
        0 3px 10px rgba(65,29,37,.025);
}

/* ======================================
   LEFT FORM PANEL
====================================== */

.eo-form-panel {
    background: var(--eo-paper);
    display: flex;
    align-items: flex-start;

    padding: 54px clamp(46px,4.5vw,74px) 40px;
}

.eo-form-inner {
    width: 100%;
    max-width: 680px;
    margin: 0 auto;
}

.eo-form-panel h1 {
    font-size: clamp(38px,3.2vw,46px);
    font-weight: 800;
    line-height: 1.14;
    letter-spacing: -1.6px;

    color: var(--eo-ink);
    margin: 0 0 10px;
}

.eo-subtitle {
    font-size: 16px;
    line-height: 1.5;
    color: var(--eo-muted);
    margin: 0;
}

/* ======================================
   TWO STEP PROGRESS NAVIGATION
====================================== */

.eo-progress {
    display: grid;
    grid-template-columns: minmax(0,1fr);
    gap: 24px;

    margin: 32px 0 28px;
}

.eo-progress-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: 13px;

    min-height: 68px;
    padding: 4px 0 20px;
    color: #948782;
}

.eo-progress-item[hidden],
.eo-progress-item:not(.active) {
    display: none;
}

.eo-progress-item .eo-step-number,
.eo-progress-item .eo-step-copy {
    transform-origin: left center;
}

/* BOTTOM PROGRESS LINE */

.eo-progress-item::after {
    content: "";
    position: absolute;

    left: 0;
    right: 0;
    bottom: 0;

    height: 3px;
    border-radius: 10px;
    background: #EDE4DF;
}

.eo-progress-item.active::after,
.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]::after {
    background: var(--eo-brown);
}

/* STEP NUMBER */

.eo-step-number {
    position: relative;
    flex: 0 0 40px;

    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    border-radius: 50%;
    border: 1.5px solid #E4D9D3;

    background: var(--eo-paper);
    color: #A89992;

    font-size: 13px;
    font-weight: 750;
}

.eo-progress-item.active .eo-step-number {
    background: var(--eo-brown);
    border-color: var(--eo-brown);
    color: #FFF9F4;
}

/* STEP LABELS */

.eo-step-copy strong {
    display: block;

    font-size: 14.5px;
    font-weight: 700;
    color: #8A7D78;
}

.eo-step-copy > span {
    display: block;
    font-size: 12px;
    line-height: 1.38;
    margin-top: 4px;
    color: #A29690;
}

.eo-progress-item.active .eo-step-copy strong {
    color: var(--eo-ink);
}

/* COMPLETED STEP */

.eo-complete-check {
    display: none;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-step-number {
    background: #F1E9E3;
    border-color: #E4D8D0;
    font-size: 0;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-complete-check {
    display: grid; place-items: center; position: absolute; inset: 0;
    color: var(--eo-brown); font-size: 19px;
}

.eo-progress[data-step="2"]
.eo-progress-item[data-indicator="1"]
.eo-step-copy strong {
    color: var(--eo-ink);
}

/* ======================================
   STEP CONTENT
====================================== */

.eo-form-step {
    display: none;
    animation: none;
}

.eo-form-step.is-active {
    display: block;
}


.eo-section-label {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.65px;
    color: #A48D88;

    margin: 0 0 19px;
}

/* ======================================
   FORM GRID
====================================== */

.eo-form-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));

    column-gap: 20px;
    row-gap: 23px;
}

.eo-field {
    min-width: 0;
}

.eo-field.span2 {
    grid-column: 1 / -1;
}

.eo-field > label {
    display: block;

    font-size: 14px;
    font-weight: 700;
    color: var(--eo-ink);

    margin-bottom: 10px;
}

/* ======================================
   INPUTS
====================================== */

.eo-input-wrap {
    position: relative;
}

.eo-input-wrap input,
.eo-input-wrap select {
    display: block;

    width: 100%;
    height: 59px;

    background: var(--eo-input);
    border: 1.5px solid var(--eo-line);
    border-radius: 12px;

    padding: 0 17px;

    color: var(--eo-ink);
    font-size: 15px;

    outline: none;

    transition:
        background .15s,
        border-color .15s,
        box-shadow .15s;
}

.eo-input-wrap input::placeholder {
    color: #A49895;
}

.eo-input-wrap input:focus,
.eo-input-wrap select:focus {
    background: #FFFFFF;
    border-color: #C94A5E;
    box-shadow: 0 0 0 4px rgba(200,16,46,.08);
}

.eo-input-wrap input[data-password-strength],
.eo-input-wrap input[data-password-confirm] {
    padding-right: 47px;
}

/* SELECT DROPDOWN */

.eo-input-wrap select {
    cursor: pointer;
    padding-right: 38px;
    color: #857672;
}

.eo-input-wrap select:disabled {
    cursor: not-allowed;
}

.eo-signup .eo-phone-control {
    display: flex;
    width: 100%;
    height: 48px;
    border: 1.5px solid var(--eo-line);
    border-radius: 12px;
    background: var(--eo-input);
    transition: background .15s, border-color .15s, box-shadow .15s;
}

.eo-signup .eo-phone-control:focus-within {
    background: #FFFFFF;
    border-color: #C94A5E;
    box-shadow: 0 0 0 4px rgba(200,16,46,.08);
}

.eo-signup .eo-phone-control.has-error { border-color: #B94142; }

.eo-signup .eo-phone-country {
    position: relative;
    flex: 0 0 104px;
    min-width: 0;
    border-right: 1px solid var(--eo-line);
}

.eo-signup .eo-phone-country::after {
    content: "";
    position: absolute;
    right: 10px;
    top: 19px;
    width: 6px;
    height: 6px;
    border: solid #857672;
    border-width: 0 1.5px 1.5px 0;
    transform: rotate(45deg);
    pointer-events: none;
}

.eo-auth-page.eo-signup .eo-phone-control input,
.eo-auth-page.eo-signup .eo-phone-control select {
    height: 100%;
    border: 0;
    background: transparent;
    box-shadow: none;
}

.eo-signup .eo-phone-control input {
    flex: 1 1 0;
    min-width: 0;
    width: 0;
    padding: 0 12px;
    border-radius: 0 12px 12px 0;
}

.eo-signup .eo-phone-control select {
    width: 100%;
    padding: 0 24px 0 10px;
    border-radius: 12px 0 0 12px;
    appearance: none;
    font-size: 13px;
}

.eo-signup .eo-phone-control.is-enhanced select { display: none; }
.eo-signup .eo-phone-control select option,
.eo-signup .eo-phone-control select optgroup { color: var(--eo-ink); background: #FFFFFF; }

.eo-signup .eo-phone-code {
    display: none;
    position: absolute;
    inset: 0 24px 0 10px;
    align-items: center;
    gap: 6px;
    color: var(--eo-ink);
    font-size: 13px;
    white-space: nowrap;
    pointer-events: none;
}

.eo-signup .eo-phone-control.is-enhanced .eo-phone-code { display: flex; }
.eo-signup .eo-phone-code strong { font-size: 12px; }

.eo-signup .eo-phone-trigger {
    position: relative;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    border-radius: 12px 0 0 12px;
    background: transparent;
    cursor: pointer;
}
.eo-signup .eo-phone-trigger[hidden] { display: none; }
.eo-signup .eo-phone-trigger:hover { background: #F1E4D5; }
.eo-signup .eo-phone-trigger:focus-visible { outline: 2px solid #C94A5E; outline-offset: -3px; }

.eo-phone-picker {
    position: fixed;
    inset: auto;
    margin: 0;
    padding: 0 0 8px;
    width: 352px;
    max-width: calc(100vw - 24px);
    max-height: 420px;
    border: 1px solid #E9DFDB;
    border-radius: 16px;
    overflow: hidden;
    background: #FFFCF9;
    color: var(--eo-ink);
    box-shadow: 0 16px 44px rgba(57,41,43,.18), 0 3px 10px rgba(57,41,43,.06);
    font-family: "Segoe UI", Arial, sans-serif;
}
.eo-phone-picker[hidden] { display: none; }
.eo-phone-picker:popover-open { display: flex; flex-direction: column; }
.eo-phone-picker-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 16px 12px; }
.eo-phone-picker-header h2 { margin: 0; color: var(--eo-ink); font: 800 15px/1.3 "Segoe UI", Arial, sans-serif; text-decoration: none; }
.eo-phone-picker-header p { margin: 3px 0 0; color: #8C7C78; font-size: 12px; line-height: 1.4; }
.eo-phone-picker-close { display: grid; place-items: center; width: 30px; height: 30px; padding: 0; border: 0; border-radius: 8px; background: transparent; color: #8C7C78; cursor: pointer; }
.eo-phone-picker-close:hover { background: #F1E4D5; color: #5A3D32; }
.eo-phone-picker-close svg { width: 17px; height: 17px; }
.eo-country-search-wrap { position: relative; margin: 0 14px 10px; flex-shrink: 0; }
.eo-country-search-wrap > svg { position: absolute; top: 12px; left: 12px; width: 17px; height: 17px; color: #A48D88; pointer-events: none; }
.eo-phone-picker .eo-country-search {
    display: block;
    width: 100%;
    height: 41px;
    padding: 0 12px 0 36px;
    border: 1px solid #E9DFDB;
    border-radius: 10px;
    outline: none;
    background: #FAF6F3;
    color: var(--eo-ink);
    font: 14px "Segoe UI", Arial, sans-serif;
}
.eo-phone-picker .eo-country-search:focus { border-color: #C94A5E; box-shadow: 0 0 0 3px rgba(200,16,46,.07); }
.eo-country-list { position: relative; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #D8B9A4 transparent; }
.eo-country-group { padding: 10px 18px 7px; color: #A48D88; font-size: 10px; font-weight: 800; letter-spacing: 1.1px; text-transform: uppercase; }
.eo-country-option { display: flex; align-items: center; gap: 10px; min-height: 46px; margin: 0 8px 3px; padding: 8px 9px; border: 1px solid transparent; border-radius: 10px; cursor: pointer; }
.eo-country-option:hover, .eo-country-option.is-active { background: #FAF2EA; }
.eo-country-option[aria-selected="true"] { border-color: #F1CCD1; background: #FFF1F1; }
.eo-country-badge { display: grid; place-items: center; flex: 0 0 30px; height: 30px; border-radius: 9px; background: #F1E4D5; color: #5A3D32; font-size: 11px; font-weight: 800; }
.eo-country-name { flex: 1; min-width: 0; color: var(--eo-ink); font-size: 13px; font-weight: 600; line-height: 1.3; }
.eo-country-dial { color: #8B6049; font-size: 12px; white-space: nowrap; }
.eo-country-check { display: grid; place-items: center; flex: 0 0 16px; color: #C8102E; visibility: hidden; }
.eo-country-check svg { width: 16px; height: 16px; }
.eo-country-option[aria-selected="true"] .eo-country-check { visibility: visible; }
.eo-country-empty { padding: 16px; margin: 0; color: #8C7C78; text-align: center; font-size: 13px; }
.eo-country-empty[hidden] { display: none; }

/* ======================================
   PASSWORD EYE ICON
====================================== */

.eo-eye {
    position: absolute;
    right: 9px;
    top: 50%;

    transform: translateY(-50%);

    width: 40px;
    height: 40px;

    display: grid;
    place-items: center;

    padding: 0;
    border: none;
    border-radius: 9px;

    background: transparent;
    color: #94827E;
    cursor: pointer;
}

.eo-eye svg {
    width: 21px;
    height: 21px;
}

.eo-eye:hover {
    color: #A6102D;
    background: #FFF0EE;
}

.eo-eye .eo-eye-closed { display: none; }
.eo-eye.is-visible .eo-eye-open { display: none; }
.eo-eye.is-visible .eo-eye-closed { display: block; }

/* ======================================
   GENDER OPTIONS
====================================== */

.eo-gender-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.eo-gender-options label {
    position: relative;
    cursor: pointer;
}

.eo-gender-options input {
    position: absolute;
    opacity: 0;
}

.eo-gender-options span {
    display: grid;
    place-items: center;

    height: 59px;

    background: var(--eo-input);
    border: 1.5px solid var(--eo-line);
    border-radius: 12px;

    font-size: 15px;
    color: var(--eo-muted);
}

.eo-gender-options input:checked + span {
    background: #FFF0EE;
    border-color: var(--eo-red);
    color: #A6102D;
    font-weight: 700;
}

.eo-gender-options input:focus-visible + span {
    outline: 3px solid #E9AD70;
    outline-offset: 3px;
}

/* ======================================
   ACTION BUTTONS
====================================== */

.eo-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 29px;
}

/* MAIN RED BUTTON */

.eo-primary {
    flex: 1;

    display: flex;
    justify-content: space-between;
    align-items: center;

    height: 61px;

    padding: 0 23px;
    border: none;
    border-radius: 12px;

    background: linear-gradient(
        105deg,
        #C8102E 0%,
        #A30B28 100%
    );

    color: #FFFFFF;
    font-size: 16px;
    font-weight: 800;

    cursor: pointer;

    box-shadow:
        0 11px 23px rgba(149,13,41,.17);

    transition:
        transform .15s,
        box-shadow .15s;
}

.eo-primary:hover {
    transform: translateY(-2px);
    box-shadow:
        0 13px 25px rgba(149,13,41,.25);
}

.eo-primary .arrow {
    font-size: 23px;
    font-weight: 400;
}

/* BACK BUTTON */

.eo-back {
    min-width: 110px;
    height: 61px;

    background: transparent;
    border: 1.5px solid #E4D7D2;
    border-radius: 12px;

    color: #6C5A56;
    font-size: 14px;
    font-weight: 700;

    cursor: pointer;
}

/* SIGN IN LINK */

.eo-bottom-link {
    margin-top: 22px;
    text-align: center;

    font-size: 14px;
    color: var(--eo-muted);
}

.eo-bottom-link a {
    color: #A6102D;
    font-weight: 800;
    text-decoration: none;
    margin-left: 4px;
}

/* ======================================
   RIGHT FOOD SHOWCASE V11
====================================== */

.eo-right-showcase {
    position: relative;
    min-height: 0;

    background: var(--eo-brown);
    overflow: hidden;

    isolation: isolate;
}

/* EXPANDED RESTAURANT PHOTO */

.eo-right-showcase::before {
    content: "";

    position: absolute;
    inset: 0;
    z-index: 0;

    background-image:
        linear-gradient(
            180deg,
            #39292B 0%,
            #39292B 12%,
            rgba(57,41,43,.94) 22%,
            rgba(57,41,43,.60) 32%,
            rgba(57,41,43,.02) 51%,
            rgba(57,41,43,0) 61%
        ),
        url("image/login.jpg?v=20261024-signup1");

    background-position: center bottom;
    background-size: 100% auto;
    background-repeat: no-repeat;
}

/* RIGHT HEADING */

.eo-showcase-title {
    position: relative;
    z-index: 2;

    padding: 70px 52px 0;
}

.eo-showcase-title h2 {
    margin: 0;

    color: #FFF8F2;
    font-size: clamp(38px,3.6vw,54px);
    font-weight: 800;
    line-height: 1.13;
    letter-spacing: -1.6px;
}

/* CHAMPAGNE ITALIC */

.eo-showcase-title h2 em {
    color: var(--eo-peach);

    font-family: Georgia, "Times New Roman", serif;
    font-size: 1.02em;
    font-style: italic;
    font-weight: 700;

    letter-spacing: -1.3px;
}

/* ======================================
   MESSAGES AND SMALL TEXT
====================================== */

.eo-step-helper {
    margin: 18px 0 0;
    color: #8C7C78;
    font-size: 13px;
    line-height: 1.45;
}

.eo-server-error {
    display: block;
    margin-top: 7px;
    color: #B94142;
    font-size: 12px;
    line-height: 1.4;
}

.eo-server-error[hidden] {
    display: none;
}

.eo-signup button:focus-visible,
.eo-signup a:focus-visible {
    outline: 3px solid #E9AD70;
    outline-offset: 3px;
}

/* SMALLER DESKTOP SCREENS */

@media (max-width: 1200px) {
    #main.eo-signup {
        width: calc(100% - 48px);
    }

    .eo-form-panel {
        padding-left: 38px;
        padding-right: 38px;
    }

    .eo-showcase-title {
        padding-left: 34px;
        padding-right: 25px;
    }

    .eo-progress {
        gap: 18px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .eo-signup * {
        animation: none !important;
        transition: none !important;
    }
}

/* Local registration feedback and semantic field groups. */
.eo-signup .eo-gender-field { border: 0; padding: 0; margin: 0; }
.eo-signup .eo-gender-field > legend { padding: 0; margin-bottom: 10px; color: var(--eo-ink); font-size: 14px; font-weight: 700; }
.eo-signup .eo-eye svg { stroke-linecap: round; stroke-linejoin: round; }
.eo-signup .eo-complete-check svg { width: 18px; height: 18px; }
.eo-signup .password-feedback { font-size: 12px; line-height: 1.4; }
.eo-signup .eo-section-label:focus { outline: none; }
.eo-signup [aria-invalid="true"] { border-color: #B94142; }
.eo-signup .eo-form-step[hidden] { display: none; }
.eo-signup .eo-password-reminder { color: #A6102D; }

/* Let both steps reserve the taller content height so actions never jump. */
.eo-signup #signup-form.has-step-navigation {
    display: grid;
}

.eo-signup #signup-form.has-step-navigation .eo-form-step {
    display: flex;
    flex-direction: column;
    grid-area: 1 / 1;
    min-width: 0;
    visibility: hidden;
    pointer-events: none;
}

.eo-signup #signup-form.has-step-navigation .eo-form-step.is-active {
    visibility: visible;
    pointer-events: auto;
}

.eo-signup #signup-form.has-step-navigation .eo-actions {
    margin-top: auto;
    padding-top: 22px;
}

</style>
<noscript><style>
.eo-signup .eo-form-step { display: block; animation: none; }
.eo-signup .eo-progress, .eo-signup #next-btn, .eo-signup #back-btn, .eo-signup .eo-eye { display: none; }
.eo-signup .eo-form-step + .eo-form-step { margin-top: 32px; }
</style></noscript>
</head>
<body>

<?php easyorder_render_customer_header(); ?>

<div id="navbar" class="customer-site-navbar">
<?php easyorder_render_customer_navigation_links(isset($_SESSION["customer_id"])); ?>
</div>

<main id="main" class="eo-signup eo-auth-page" data-auth-view="signup" data-start-step="<?php echo $signup_start_step; ?>">
<div class="eo-auth-card eo-auth-frame">
<section class="eo-form-panel eo-auth-form-panel" aria-labelledby="signup-title">
<div class="eo-form-inner">
<h1 id="signup-title">Create your account</h1>
<p class="eo-subtitle" id="signup-subtitle" aria-live="polite"><?php echo $signup_start_step===2 ? "Just one more step to get started." : "A few details, then you're ready to order."; ?></p>
<div class="eo-progress" data-step="<?php echo $signup_start_step; ?>" role="list" aria-label="Registration progress, step <?php echo $signup_start_step; ?> of 2">
<div class="eo-progress-item<?php echo $signup_start_step===1 ? " active" : ""; ?>" data-indicator="1" role="listitem"<?php echo $signup_start_step===1 ? ' aria-current="step"' : ' hidden'; ?>>
<span class="eo-step-number">01<span class="eo-complete-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg></span></span>
<div class="eo-step-copy"><strong>Account details</strong><span>Sign-in information</span></div>
</div>
<div class="eo-progress-item<?php echo $signup_start_step===2 ? " active" : ""; ?>" data-indicator="2" role="listitem"<?php echo $signup_start_step===2 ? ' aria-current="step"' : ' hidden'; ?>>
<span class="eo-step-number">02</span><div class="eo-step-copy"><strong>Personal details</strong><span>Just a few more details</span></div>
</div>
</div>
<?php if(isset($register_errors["general"])) { ?><p class="form-message form-message-error" role="alert"><?php echo register_h($register_errors["general"]); ?></p><?php } ?>
<form id="signup-form" method="post" action="register.php" novalidate>
<input type="hidden" name="register_csrf" value="<?php echo register_h($_SESSION["register_csrf"]); ?>">
<input type="hidden" name="signupbtn" value="1">
<div id="phone-country-picker" class="eo-phone-picker" popover="auto" role="dialog" aria-labelledby="phone-country-picker-title" hidden>
<div class="eo-phone-picker-header"><div><h2 id="phone-country-picker-title">Phone country</h2><p>Select your calling code</p></div><button type="button" class="eo-phone-picker-close" aria-label="Close country selection" tabindex="-1"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
<div class="eo-country-search-wrap"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg><input id="phone-country-search" class="eo-country-search" type="search" placeholder="Search country or code" autocomplete="off" maxlength="80" aria-label="Search countries or calling codes" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="phone-country-list"></div>
<div id="phone-country-list" class="eo-country-list" role="listbox" aria-label="Countries and calling codes"></div>
<p class="eo-country-empty" role="status" hidden>No countries found.</p>
</div>
<div class="eo-form-step<?php echo $signup_start_step===1 ? " is-active" : ""; ?>" data-step="1" aria-labelledby="signup-account-label">
<p class="eo-section-label" id="signup-account-label" tabindex="-1">YOUR ACCOUNT</p>
<div class="eo-form-grid">
<div class="eo-field">
<label for="cust_name">Full name</label>
<div class="eo-input-wrap"><input id="cust_name" name="cust_name" type="text" placeholder="Your full name" autocomplete="name" minlength="2" maxlength="100" value="<?php echo register_h($register_values["name"]); ?>"<?php echo signup_field_aria("name","cust_name",""); ?> required></div>
<?php signup_field_error("name","cust_name"); ?>
</div>
<div class="eo-field">
<label for="cust_phone">Phone number</label>
<div class="eo-input-wrap eo-phone-control<?php if(isset($register_errors["phone"])) echo " has-error"; ?>">
<div class="eo-phone-country">
<select id="cust_phone_country" name="phone_country" aria-label="Phone country or region" autocomplete="off"<?php echo signup_field_aria("phone","cust_phone",""); ?> required>
<option value="">Country / region</option>
<?php $signup_preferred_countries = array("MY","SG","CN","ID","TH","BN","PH","IN","GB","US"); ?>
<optgroup label="Frequently selected">
<?php foreach($signup_preferred_countries as $phone_country) { $phone_details = $signup_phone_countries[$phone_country]; ?>
<option value="<?php echo register_h($phone_country); ?>" data-dial="<?php echo register_h($phone_details[1]); ?>" data-prefix="<?php echo register_h($phone_details[2]); ?>"<?php if($register_values["phone_country"]===$phone_country) echo " selected"; ?>><?php echo register_h($phone_details[0]." (+".$phone_details[1].")"); ?></option>
<?php } ?>
</optgroup>
<optgroup label="All countries and regions">
<?php foreach($signup_phone_countries as $phone_country => $phone_details) { if(in_array($phone_country,$signup_preferred_countries,true)) continue; ?>
<option value="<?php echo register_h($phone_country); ?>" data-dial="<?php echo register_h($phone_details[1]); ?>" data-prefix="<?php echo register_h($phone_details[2]); ?>"<?php if($register_values["phone_country"]===$phone_country) echo " selected"; ?>><?php echo register_h($phone_details[0]." (+".$phone_details[1].")"); ?></option>
<?php } ?>
</optgroup>
</select>
<button id="phone-country-trigger" class="eo-phone-trigger" type="button" popovertarget="phone-country-picker" aria-label="Phone country or region" aria-haspopup="dialog" aria-controls="phone-country-picker" aria-expanded="false" hidden><span class="eo-phone-code" aria-hidden="true"><strong></strong><span></span></span></button>
</div>
<input id="cust_phone" name="cust_phone" type="tel" placeholder="e.g. 123456789" autocomplete="tel-national" inputmode="tel" minlength="4" maxlength="32" value="<?php echo register_h($register_values["phone"]); ?>"<?php echo signup_field_aria("phone","cust_phone",""); ?> required>
</div>
<?php signup_field_error("phone","cust_phone"); ?>
</div>
<div class="eo-field">
<label for="cust_email">Email address</label>
<div class="eo-input-wrap"><input id="cust_email" name="cust_email" type="email" placeholder="e.g. customer@email.com" autocomplete="email" maxlength="100" value="<?php echo register_h($register_values["email"]); ?>"<?php echo signup_field_aria("email","cust_email",""); ?> required></div>
<?php signup_field_error("email","cust_email"); ?>
</div>
<div class="eo-field">
<label for="cust_confirm_email">Confirm email</label>
<div class="eo-input-wrap"><input id="cust_confirm_email" name="cust_confirm_email" type="email" placeholder="Re-enter your email" autocomplete="email" maxlength="100" value="<?php echo register_h($register_values["confirm_email"]); ?>"<?php echo signup_field_aria("confirm_email","cust_confirm_email",""); ?> required></div>
<?php signup_field_error("confirm_email","cust_confirm_email"); ?>
</div>
<div class="eo-field"><label for="cust_password">Password</label>
<div class="eo-input-wrap"><input type="password" id="cust_password" name="cust_password" placeholder="Create a password" minlength="8" maxlength="72" autocomplete="new-password" data-password-strength="register-password-strength"<?php echo signup_field_aria("password","cust_password","register-password-strength"); ?> required><button type="button" class="eo-eye" data-toggle="cust_password" aria-controls="cust_password" aria-label="Show password" aria-pressed="false">
<svg class="eo-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="eo-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="signup-eye-gap-cust_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#signup-eye-gap-cust_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg></button></div>
<small id="register-password-strength" class="password-feedback" data-state="empty" aria-live="polite">Use 8 or more characters.</small>
<?php signup_field_error("password","cust_password"); ?></div>
<div class="eo-field"><label for="cust_confirm_password">Confirm password</label>
<div class="eo-input-wrap"><input type="password" id="cust_confirm_password" name="cust_confirm_password" placeholder="Re-enter password" minlength="8" maxlength="72" autocomplete="new-password" data-password-confirm="cust_password" data-password-match="register-password-match"<?php echo signup_field_aria("confirm_password","cust_confirm_password","register-password-match"); ?> required><button type="button" class="eo-eye" data-toggle="cust_confirm_password" aria-controls="cust_confirm_password" aria-label="Show password" aria-pressed="false">
<svg class="eo-eye-open" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
<svg class="eo-eye-closed" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
<defs><mask id="signup-eye-gap-cust_confirm_password" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="white" stroke="none"/><path d="M3 3l18 18" stroke="black" stroke-width="4.6"/></mask></defs>
<g mask="url(#signup-eye-gap-cust_confirm_password)"><path d="M2 12s3.7-7 10-7 10 7 10 7-3.7 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></g><path d="M3 3l18 18"/>
</svg></button></div>
<small id="register-password-match" class="password-feedback" data-state="empty" aria-live="polite">Enter the same password again.</small>
<?php signup_field_error("confirm_password","cust_confirm_password"); ?></div>
</div>
<p id="signup-account-check-status" class="eo-server-error" role="status" aria-live="polite" hidden></p>
<div class="eo-actions"><button type="button" id="next-btn" class="eo-primary"><span>Continue to personal details</span><span class="arrow" aria-hidden="true">→</span></button></div>
</div>
<div class="eo-form-step<?php echo $signup_start_step===2 ? " is-active" : ""; ?>" data-step="2" aria-labelledby="signup-personal-label">
<p class="eo-section-label" id="signup-personal-label" tabindex="-1">A LITTLE ABOUT YOU</p>
<div class="eo-form-grid">
<fieldset class="eo-field eo-gender-field"><legend>Gender</legend><div class="eo-gender-options">
<label><input type="radio" id="gender_male" name="gender" value="Male"<?php echo signup_field_aria("gender","gender","gender-client-error"); ?> <?php if($register_values["gender"]==="Male") echo "checked"; ?>><span>Male</span></label>
<label><input type="radio" id="gender_female" name="gender" value="Female"<?php echo signup_field_aria("gender","gender","gender-client-error"); ?> <?php if($register_values["gender"]==="Female") echo "checked"; ?>><span>Female</span></label>
</div><small id="gender-client-error" class="eo-server-error" hidden>Please select your gender.</small><?php signup_field_error("gender","gender"); ?></fieldset>
<div class="eo-field">
<label for="cust_dob">Date of birth</label>
<div class="eo-input-wrap"><input id="cust_dob" name="cust_dob" type="date" min="1900-01-01" max="<?php echo date("Y-m-d"); ?>" autocomplete="bday" value="<?php echo register_h($register_values["dob"]); ?>"<?php echo signup_field_aria("dob","cust_dob",""); ?> required></div>
<?php signup_field_error("dob","cust_dob"); ?>
</div>
<div class="eo-field span2"><label for="state">State</label><div class="eo-input-wrap">
<select id="state" name="state" autocomplete="address-level1"<?php echo signup_field_aria("state","state",""); ?> required><option value="">Select your state</option>
<?php foreach($states as $state_name) { ?><option value="<?php echo register_h($state_name); ?>" <?php if($register_values["state"]===$state_name) echo "selected"; ?>><?php echo register_h($state_name); ?></option><?php } ?>
</select></div><?php signup_field_error("state","state"); ?></div>
<div class="eo-field">
<label for="cust_city">City</label>
<div class="eo-input-wrap"><select id="cust_city" name="cust_city" autocomplete="address-level2"<?php echo signup_field_aria("city","cust_city",""); ?> required>
<option value="">Select your city</option>
<?php foreach($signup_cities_by_state as $city_state => $city_names) { ?>
<optgroup label="<?php echo register_h($city_state); ?>">
<?php foreach($city_names as $city_name) { ?><option value="<?php echo register_h($city_name); ?>"<?php if($register_values["state"]===$city_state && $register_values["city"]===$city_name) echo " selected"; ?>><?php echo register_h($city_name); ?></option><?php } ?>
</optgroup>
<?php } ?>
</select></div>
<?php signup_field_error("city","cust_city"); ?>
</div>
<div class="eo-field">
<label for="cust_postcode">Postcode</label>
<div class="eo-input-wrap"><input id="cust_postcode" name="cust_postcode" type="text" placeholder="e.g. 80000" inputmode="numeric" pattern="[0-9]{5}" minlength="5" maxlength="5" autocomplete="postal-code" value="<?php echo register_h($register_values["postcode"]); ?>"<?php echo signup_field_aria("postcode","cust_postcode",""); ?> required></div>
<?php signup_field_error("postcode","cust_postcode"); ?>
</div>
</div>
<p class="eo-step-helper">Your details will be used to set up your EasyOrder account.</p>
<?php if($signup_password_reminder) { ?><p class="eo-step-helper eo-password-reminder" id="signup-password-reminder">Re-enter your password before submitting again.</p><?php } ?>
<div class="eo-actions"><button type="button" class="eo-back" id="back-btn">← Back</button><button type="submit" class="eo-primary"><span>Create account</span><span class="arrow" aria-hidden="true">→</span></button></div>
</div>
</form>
<div class="eo-bottom-link">Already have an account? <a href="login.php">Sign in ↗</a></div>
</div>
</section>
<aside class="eo-auth-showcase" aria-label="EasyOrder burger, fries, fried chicken and cola">
<img class="eo-auth-photo" src="image/login.jpg?v=20261024-auth1" alt="Burger, fries, fried chicken and cola">
<h2 class="eo-auth-showcase-heading">Good things<br><em>start here.</em></h2>
</aside>
</div>
</main>

<?php easyorder_render_customer_footer(isset($_SESSION["customer_id"])); ?>

<script data-eo-auth-init="signup">
(function () {
    "use strict";
    const root = document.querySelector(".eo-signup");
    const form = document.getElementById("signup-form");
    if (!root || !form) return;
    const steps = [...root.querySelectorAll(".eo-form-step")];
    const indicators = [...root.querySelectorAll(".eo-progress-item")];
    const progress = root.querySelector(".eo-progress");
    const subtitle = document.getElementById("signup-subtitle");
    const email = document.getElementById("cust_email");
    const confirmEmail = document.getElementById("cust_confirm_email");
    const password = document.getElementById("cust_password");
    const confirmPassword = document.getElementById("cust_confirm_password");
    const genderError = document.getElementById("gender-client-error");
    const reminder = document.getElementById("signup-password-reminder");
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
    const phone = document.getElementById("cust_phone");
    const phoneCountry = document.getElementById("cust_phone_country");
    const phoneControl = phone.closest(".eo-phone-control");
    const phoneTrigger = document.getElementById("phone-country-trigger");
    const phonePicker = document.getElementById("phone-country-picker");

    function internationalPhone() {
        const country = phoneCountry.selectedOptions[0];
        if (!country || !country.dataset.dial || phone.value.length > 32) return "";
        const clean = phone.value.trim().replace(/[\s().-]+/g, "");
        if (!/^\+?[0-9]+$/.test(clean)) return "";
        const dial = country.dataset.dial;
        const prefix = country.dataset.prefix;
        let national = clean;
        if (clean.startsWith("+") || clean.startsWith("00")) {
            const digits = clean.startsWith("+") ? clean.slice(1) : clean.slice(2);
            if (!digits.startsWith(dial)) return "";
            national = digits.slice(dial.length);
        } else if (prefix && national.startsWith(prefix) && (prefix !== "1" || national.length === 11)) {
            national = national.slice(prefix.length);
        }
        const number = dial + national;
        return /^[0-9]{4,}$/.test(national) && /^[1-9][0-9]{6,14}$/.test(number) ? number : "";
    }

    function updatePhoneCountry() {
        const country = phoneCountry.selectedOptions[0];
        phoneControl.querySelector(".eo-phone-code strong").textContent = country?.dataset.dial ? phoneCountry.value : "";
        phoneControl.querySelector(".eo-phone-code > span").textContent = country?.dataset.dial ? "+" + country.dataset.dial : "Select";
        const examples = {MY: "123456789", SG: "81234567", CN: "13800138000", US: "2015550123", GB: "7700900123"};
        phone.placeholder = examples[phoneCountry.value] ? "e.g. " + examples[phoneCountry.value] : "Phone number";
        phoneCountry.title = country?.textContent || "Select a country or region";
        phoneTrigger.setAttribute("aria-label", country?.dataset.dial ? "Phone country or region, " + country.textContent : "Select phone country or region");
        phone.setCustomValidity("");
        phoneCountry.setCustomValidity("");
    }
    phoneCountry.addEventListener("change", updatePhoneCountry);
    phone.addEventListener("input", function () { phone.setCustomValidity(""); });
    updatePhoneCountry();

    if (typeof phonePicker.showPopover === "function") {
        const search = document.getElementById("phone-country-search");
        const list = document.getElementById("phone-country-list");
        const empty = phonePicker.querySelector(".eo-country-empty");
        const records = [...phoneCountry.options].filter(function (option) { return option.dataset.dial; }).map(function (option) {
            return {value: option.value, name: option.textContent.replace(/\s+\(\+\d+\)$/, ""), dial: option.dataset.dial, group: option.parentElement.label};
        });
        let matches = [];
        let active = -1;
        let positioning = null;
        const fold = function (value) { return value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase(); };

        function highlight(index) {
            active = matches.length ? (index + matches.length) % matches.length : -1;
            matches.forEach(function (record, i) { record.element.classList.toggle("is-active", i === active); });
            if (active < 0) { search.removeAttribute("aria-activedescendant"); return; }
            const row = matches[active].element;
            search.setAttribute("aria-activedescendant", row.id);
            if (row.offsetTop < list.scrollTop) list.scrollTop = row.offsetTop;
            else if (row.offsetTop + row.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = row.offsetTop + row.offsetHeight - list.clientHeight;
        }

        function renderCountries() {
            const query = fold(search.value.trim());
            const codeQuery = /^\+?\d+$/.test(query) ? query.replace(/^\+/, "") : "";
            matches = records.filter(function (record) {
                return codeQuery ? record.dial.startsWith(codeQuery) : fold(record.name + " " + record.value).includes(query);
            });
            list.replaceChildren();
            let group = "";
            matches.forEach(function (record) {
                const label = query ? "Search results" : record.group;
                if (group !== label) {
                    group = label;
                    const heading = document.createElement("div");
                    heading.className = "eo-country-group";
                    heading.setAttribute("role", "presentation");
                    heading.textContent = label;
                    list.appendChild(heading);
                }
                const row = document.createElement("div");
                row.className = "eo-country-option";
                row.id = "phone-country-option-" + record.value;
                row.dataset.country = record.value;
                row.setAttribute("role", "option");
                row.setAttribute("aria-selected", String(record.value === phoneCountry.value));
                [["eo-country-badge", record.value], ["eo-country-name", record.name], ["eo-country-dial", "+" + record.dial]].forEach(function (part) {
                    const span = document.createElement("span");
                    span.className = part[0];
                    span.textContent = part[1];
                    row.appendChild(span);
                });
                const check = document.createElement("span");
                check.className = "eo-country-check";
                check.setAttribute("aria-hidden", "true");
                check.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6"/></svg>';
                row.appendChild(check);
                record.element = row;
                list.appendChild(row);
            });
            empty.hidden = matches.length !== 0;
            list.scrollTop = 0;
            const selected = query ? -1 : matches.findIndex(function (record) { return record.value === phoneCountry.value; });
            highlight(selected < 0 ? 0 : selected);
        }

        function positionPicker() {
            if (!phonePicker.matches(":popover-open")) return;
            const anchor = phoneControl.getBoundingClientRect();
            const below = innerHeight - anchor.bottom - 20;
            const above = anchor.top - 20;
            const downward = below >= Math.min(350, above);
            const height = Math.min(420, Math.max(170, downward ? below : above));
            phonePicker.style.width = Math.min(352, innerWidth - 24) + "px";
            phonePicker.style.maxHeight = height + "px";
            list.style.maxHeight = Math.max(60, height - 126) + "px";
            phonePicker.style.left = Math.max(12, Math.min(anchor.left, innerWidth - phonePicker.offsetWidth - 12)) + "px";
            phonePicker.style.top = Math.max(12, downward ? anchor.bottom + 8 : anchor.top - phonePicker.offsetHeight - 8) + "px";
        }

        function closePicker(focusTrigger) {
            if (phonePicker.matches(":popover-open")) phonePicker.hidePopover();
            if (focusTrigger) phoneTrigger.focus({preventScroll: true});
        }

        function openPicker() {
            if (phonePicker.matches(":popover-open")) { closePicker(true); return; }
            search.value = "";
            renderCountries();
            phonePicker.showPopover();
            positionPicker();
            highlight(active);
            search.focus({preventScroll: true});
        }

        function chooseCountry(value) {
            phoneCountry.value = value;
            phoneCountry.dispatchEvent(new Event("change", {bubbles: true}));
            closePicker(false);
            phone.focus({preventScroll: true});
        }

        phoneCountry.hidden = true;
        phonePicker.hidden = false;
        phoneTrigger.hidden = false;
        phoneControl.classList.add("is-enhanced");
        phoneTrigger.addEventListener("click", function (event) { event.preventDefault(); openPicker(); });
        phoneTrigger.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown" || event.key === "ArrowUp") { event.preventDefault(); openPicker(); }
        });
        phonePicker.querySelector(".eo-phone-picker-close").addEventListener("click", function () { closePicker(true); });
        search.addEventListener("input", function () { renderCountries(); positionPicker(); });
        search.addEventListener("keydown", function (event) {
            event.stopPropagation();
            if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                event.preventDefault(); highlight(active + (event.key === "ArrowDown" ? 1 : -1));
            } else if (event.key === "Home" || event.key === "End") {
                event.preventDefault(); highlight(event.key === "Home" ? 0 : matches.length - 1);
            } else if (event.key === "Enter") {
                event.preventDefault(); if (active >= 0) chooseCountry(matches[active].value);
            } else if (event.key === "Escape") {
                event.preventDefault(); closePicker(true);
            } else if (event.key === "Tab") {
                event.preventDefault(); closePicker(false);
                (event.shiftKey ? phoneTrigger : phone).focus({preventScroll: true});
            }
        });
        list.addEventListener("pointerdown", function (event) { if (event.target.closest("[data-country]")) event.preventDefault(); });
        list.addEventListener("click", function (event) {
            const option = event.target.closest("[data-country]");
            if (option) chooseCountry(option.dataset.country);
        });
        phonePicker.addEventListener("toggle", function (event) {
            const expanded = event.newState === "open";
            phoneTrigger.setAttribute("aria-expanded", String(expanded));
            search.setAttribute("aria-expanded", String(expanded));
            if (positioning) positioning.abort();
            if (expanded) {
                positioning = new AbortController();
                addEventListener("resize", positionPicker, {signal: positioning.signal});
                addEventListener("scroll", function (scrollEvent) {
                    if (!(scrollEvent.target instanceof Node) || !phonePicker.contains(scrollEvent.target)) positionPicker();
                }, {capture: true, passive: true, signal: positioning.signal});
            }
        });
        const observer = new MutationObserver(function () {
            if (!form.isConnected) {
                if (positioning) positioning.abort();
                observer.disconnect();
            } else if (root.inert || root.dataset.authView !== "signup") {
                closePicker(false);
            }
        });
        observer.observe(root, {childList: true, subtree: true, attributes: true, attributeFilter: ["inert", "data-auth-view"]});
    }
    const state = document.getElementById("state");
    const city = document.getElementById("cust_city");
    const citiesByState = new Map([...city.querySelectorAll("optgroup")].map(function (group) {
        return [group.label, [...group.querySelectorAll("option")].map(function (option) { return option.value; })];
    }));

    function updateCityOptions(selectedCity) {
        const cities = citiesByState.get(state.value) || [];
        city.replaceChildren(new Option(cities.length ? "Select your city" : "Select your state first", ""));
        cities.forEach(function (name) { city.add(new Option(name, name)); });
        city.disabled = cities.length === 0;
        city.value = cities.includes(selectedCity) ? selectedCity : "";
        city.setCustomValidity("");
    }
    state.addEventListener("change", function () { updateCityOptions(""); });
    updateCityOptions(city.value);
    let progressAnimations = [];
    let currentStep = 1;
    form.classList.add("has-step-navigation");

    function showStep(number, focus) {
        const changed = number !== currentStep;
        progressAnimations.forEach(function (animation) { animation.cancel(); });
        progressAnimations = [];
        currentStep = number;
        steps.forEach(function (step) {
            const active = step.dataset.step === String(number);
            step.classList.toggle("is-active", active);
            step.hidden = !active;
        });
        indicators.forEach(function (item) {
            const active = item.dataset.indicator === String(number);
            item.classList.toggle("active", active);
            item.hidden = !active;
            if (active) item.setAttribute("aria-current", "step");
            else item.removeAttribute("aria-current");
            if (active && changed && focus && !reducedMotion.matches) {
                item.querySelectorAll(".eo-step-number, .eo-step-copy").forEach(function (label) {
                    if (label.animate) progressAnimations.push(label.animate([
                        {opacity: 0, transform: "translateY(6px) scale(.98)"},
                        {opacity: 1, transform: "translateY(0) scale(1)"}
                    ], {duration: 240, easing: "cubic-bezier(.2,.8,.2,1)"}));
                });
            }
        });
        progress.dataset.step = String(number);
        progress.setAttribute("aria-label", "Registration progress, step " + number + " of 2");
        subtitle.textContent = number === 1
            ? "A few details, then you're ready to order."
            : "Just one more step to get started.";
        if (reminder) reminder.hidden = password.value !== "" && confirmPassword.value !== "";
        if (focus) steps[number - 1].querySelector(".eo-section-label").focus({preventScroll: true});
    }

    function updateMatches() {
        const matchingEmail = email.value.trim().toLowerCase() === confirmEmail.value.trim().toLowerCase();
        confirmEmail.setCustomValidity(confirmEmail.value !== "" && !matchingEmail ? "Email addresses do not match." : "");
        confirmPassword.setCustomValidity(confirmPassword.value !== "" && confirmPassword.value !== password.value ? "Passwords do not match." : "");
    }

    function validateStep(number) {
        const panel = steps[number - 1];
        panel.querySelectorAll("input:not([type='password']):not([data-password-strength]):not([data-password-confirm]):not([type='radio'])").forEach(function (field) {
            if (field.value !== field.value.trim()) field.value = field.value.trim();
        });
        panel.querySelectorAll("input:not([type='radio']), select").forEach(function (field) { field.setCustomValidity(""); });
        if (number === 1) {
            updateMatches();
            phoneCountry.setCustomValidity(phoneCountry.selectedOptions[0]?.dataset.dial ? "" : "Select your phone country or region.");
            phone.setCustomValidity(internationalPhone() ? "" : "Select a country and enter a valid phone number.");
        }
        if (number === 2) {
            const gender = panel.querySelector('input[name="gender"]:checked');
            genderError.hidden = !!gender;
            panel.querySelectorAll('input[name="gender"]').forEach(function (field) {
                if (gender) field.removeAttribute("aria-invalid");
                else field.setAttribute("aria-invalid", "true");
            });
            if (!gender) {
                panel.querySelector('input[name="gender"]').focus();
                return false;
            }
        }
        for (const field of panel.querySelectorAll("input:not([type='radio']), select")) {
            if (field.value !== "" && field.minLength > 0 && field.value.length < field.minLength) {
                field.setCustomValidity("Please use at least " + field.minLength + " characters.");
            }
            if (field.maxLength > 0 && field.value.length > field.maxLength) {
                field.setCustomValidity("Please use no more than " + field.maxLength + " characters.");
            }
            if (!field.checkValidity()) {
                if (field === phoneCountry && !phoneTrigger.hidden) {
                    phoneTrigger.focus({preventScroll: true});
                    phoneTrigger.click();
                    return false;
                }
                field.focus();
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    const nextButton = document.getElementById("next-btn");
    const nextLabel = nextButton.querySelector("span");
    const nextText = nextLabel.textContent;
    const checkStatus = document.getElementById("signup-account-check-status");
    let checkController = null;
    let checkVersion = 0;

    function clearAccountFeedback() {
        [email, phone].forEach(function (field) {
            field.setCustomValidity("");
            field.removeAttribute("aria-invalid");
            const ids = [field.id + "-error", field.id + "-availability-error"];
            ids.forEach(function (id) { const message = document.getElementById(id); if (message) message.hidden = true; });
            const descriptions = (field.getAttribute("aria-describedby") || "").split(/\s+/).filter(function (id) { return id && !ids.includes(id); });
            if (descriptions.length) field.setAttribute("aria-describedby", descriptions.join(" "));
            else field.removeAttribute("aria-describedby");
        });
        phoneCountry.removeAttribute("aria-invalid");
        phoneControl.classList.remove("has-error");
        checkStatus.hidden = true;
    }

    function showAccountFeedback(errors) {
        let first = null;
        [["email", email], ["phone", phone]].forEach(function (entry) {
            const field = entry[1];
            if (!errors[entry[0]]) return;
            const id = field.id + "-availability-error";
            let message = document.getElementById(id);
            if (!message) {
                message = document.createElement("small");
                message.id = id;
                message.className = "eo-server-error";
                field.closest(".eo-field").appendChild(message);
            }
            message.textContent = errors[entry[0]];
            message.hidden = false;
            field.setAttribute("aria-invalid", "true");
            field.setAttribute("aria-describedby", id);
            if (field === phone) phoneControl.classList.add("has-error");
            if (!first) first = field;
        });
        if (errors.general) { checkStatus.textContent = errors.general; checkStatus.hidden = false; }
        if (first) first.focus({preventScroll: true});
    }

    function accountSnapshot() {
        return JSON.stringify([email.value.trim().toLowerCase(), phoneCountry.value, phone.value.trim()]);
    }

    function invalidateAccountCheck() {
        checkVersion++;
        if (checkController) checkController.abort();
        checkController = null;
        nextButton.disabled = false;
        nextButton.removeAttribute("aria-busy");
        nextLabel.textContent = nextText;
        clearAccountFeedback();
    }

    async function goNext() {
        if (checkController) return;
        clearAccountFeedback();
        if (!validateStep(1)) return;
        if (!window.fetch || !window.AbortController) { showStep(2, true); return; }
        const snapshot = accountSnapshot();
        const version = ++checkVersion;
        const controller = new AbortController();
        checkController = controller;
        nextButton.disabled = true;
        nextButton.setAttribute("aria-busy", "true");
        nextLabel.textContent = "Checking details…";
        const body = new URLSearchParams({signup_check: "1", register_csrf: form.elements.register_csrf.value,
            cust_email: email.value.trim(), phone_country: phoneCountry.value, cust_phone: phone.value.trim()});
        try {
            const response = await fetch(form.action, {method: "POST", body: body, credentials: "same-origin", cache: "no-store", signal: controller.signal, headers: {Accept: "application/json"}});
            const result = await response.json();
            if (version !== checkVersion || !form.isConnected || snapshot !== accountSnapshot()) return;
            if (result.available === true && response.ok) {
                if (validateStep(1)) showStep(2, true);
            } else if (result.errors && Object.keys(result.errors).length) {
                showAccountFeedback(result.errors);
            } else {
                throw new Error("Account check unavailable");
            }
        } catch (error) {
            if (error.name !== "AbortError" && version === checkVersion && form.isConnected) {
                showAccountFeedback({general: "We couldn't check your account details. Please try again."});
            }
        } finally {
            if (version === checkVersion) {
                checkController = null;
                nextButton.disabled = false;
                nextButton.removeAttribute("aria-busy");
                nextLabel.textContent = nextText;
            }
        }
    }
    [email, phone].forEach(function (field) { field.addEventListener("input", invalidateAccountCheck); });
    phoneCountry.addEventListener("change", invalidateAccountCheck);
    phoneTrigger.addEventListener("click", function () { if (checkController) invalidateAccountCheck(); });
    const checkObserver = new MutationObserver(function () {
        if (!form.isConnected) { if (checkController) checkController.abort(); checkObserver.disconnect(); }
    });
    checkObserver.observe(root, {childList: true, subtree: true});
    nextButton.addEventListener("click", goNext);
    document.getElementById("back-btn").addEventListener("click", function () { showStep(1, true); });
    [email, confirmEmail, password, confirmPassword].forEach(function (field) {
        field.addEventListener("input", updateMatches);
    });
    form.querySelectorAll('input[name="gender"]').forEach(function (field) {
        field.addEventListener("change", function () {
            genderError.hidden = true;
            form.querySelectorAll('input[name="gender"]').forEach(function (input) { input.removeAttribute("aria-invalid"); });
        });
    });
    root.querySelectorAll(".eo-eye").forEach(function (button) {
        button.addEventListener("click", function () {
            const field = document.getElementById(button.dataset.toggle);
            const visible = field.type === "password";
            field.type = visible ? "text" : "password";
            button.classList.toggle("is-visible", visible);
            button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
            button.setAttribute("aria-pressed", String(visible));
            field.focus();
        });
    });
    form.addEventListener("keydown", function (event) {
        if (event.key === "Enter" && !event.isComposing && currentStep === 1 && event.target.matches("input")) {
            event.preventDefault();
            goNext();
        }
    });
    form.addEventListener("submit", function (event) {
        if (currentStep === 1) {
            event.preventDefault();
            goNext();
            return;
        }
        showStep(1);
        if (!validateStep(1)) {
            event.preventDefault();
            return;
        }
        showStep(2);
        if (!validateStep(2)) event.preventDefault();
    });
    showStep(Number(root.dataset.startStep) === 2 ? 2 : 1);
    const firstError = root.querySelector('.eo-form-step.is-active [aria-invalid="true"]');
    if (firstError) {
        const target = firstError === phoneCountry && !phoneTrigger.hidden
            ? (phoneCountry.selectedOptions[0]?.dataset.dial ? phone : phoneTrigger) : firstError;
        target.focus({preventScroll: true});
    }
})();

</script>

</body>
</html>
