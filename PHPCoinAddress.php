<?php
/* ****************************************************************************

PHPCoinAddress is a PHP object that creates public/private address key pairs for:
Bitcoin, Namecoin, Litecoin, PPCoin and many other cryptocoins.

Version 0.2.0.pre

* Info: https://github.com/zamgo/PHPCoinAddress
* Download ZIP: https://github.com/zamgo/PHPCoinAddress/archive/master.zip
* Git clone: https://github.com/zamgo/PHPCoinAddress.git


****************************************************************************
Example Usage:

require_once 'PHPCoinAddress.php';
// CoinAddress::set_debug(true);      // optional - show debugging messages
// CoinAddress::set_reuse_keys(true); // optional - use same key for all addresses
$coin = CoinAddress::bitcoin();  
print 'public (base58): ' . $coin['public'] . "\n";
print 'public (Hex)   : ' . $coin['public_hex'] . "\n";
print 'private (WIF)  : ' . $coin['private'] . "\n";
print 'private (Hex)  : ' . $coin['private_hex'] . "\n"; 

****************************************************************************
Notes:
* modded from https://gist.github.com/scintill/3549107
* includes Pure PHP Elliptic Curve Cryptography Library from https://github.com/mdanter/phpecc
* Requires GMP or bcmath extension (GMP preferred for better performance)

****************************************************************************
MIT License:

Copyright (C) 2013 PHPCoinAddress Developers

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

**************************************************************************** */

// START: Setup
if( defined('MAX_BASE') && MAX_BASE != 256 ) { 
    print 'ERROR: MAX_BASE must be 256.'; exit;
} else {
    define('MAX_BASE', 256); // so we can use bcmath_Utils::bin2bc with "base256"
}
if (!defined('USE_EXT')) {
    if (extension_loaded('gmp')) {
        define('USE_EXT', 'GMP');
    } else if(extension_loaded('bcmath')) {
        define('USE_EXT', 'BCMATH');
    } else {
        die('GMP or BCMATH required');
    }
}
// END: Setup

// START: PHP ECC Libs - Compacted

// originals @ https://github.com/mdanter/phpecc
/***********************************************************************
Copyright (C) 2012 Matyas Danter

Permission is hereby granted, free of charge, to any person obtaining
a copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included
in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES
OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE,
ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
*************************************************************************/
// https://github.com/mdanter/phpecc/blob/master/classes/interface/CurveFpInterface.php
interface CurveFpInterface{public function __construct($prime,$a,$b);public function contains($x,$y);public function getA();public function getB();public function getPrime();public static function cmp(CurveFp$cp1,CurveFp$cp2);}
// https://github.com/mdanter/phpecc/blob/master/classes/CurveFp.php
class CurveFp implements CurveFpInterface{protected $a=0;protected $b=0;protected $prime=0;public function __construct($prime,$a,$b){$this->a=$a;$this->b=$b;$this->prime=$prime;}public function contains($x,$y){$eq_zero=null;if(extension_loaded('gmp')&&USE_EXT=='GMP'){$eq_zero=gmp_cmp(gmp_Utils::gmp_mod2(gmp_sub(gmp_pow($y,2),gmp_add(gmp_add(gmp_pow($x,3),gmp_mul($this->a,$x)),$this->b)),$this->prime),0);if($eq_zero==0){return true;}else{return false;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$eq_zero=bccomp(bcmod(bcsub(bcpow($y,2),bcadd(bcadd(bcpow($x,3),bcmul($this->a,$x)),$this->b)),$this->prime),0);if($eq_zero==0){return true;}else{return false;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public function getA(){return $this->a;}public function getB(){return $this->b;}public function getPrime(){return $this->prime;}public static function cmp(CurveFp$cp1,CurveFp$cp2){$same=null;if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(gmp_cmp($cp1->a,$cp2->a)==0&&gmp_cmp($cp1->b,$cp2->b)==0&&gmp_cmp($cp1->prime,$cp2->prime)==0){return 0;}else{return 1;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(bccomp($cp1->a,$cp2->a)==0&&bccomp($cp1->b,$cp2->b)==0&&bccomp($cp1->prime,$cp2->prime)==0){return 0;}else{return 1;}}else{throw new ErrorException("Please install BCMATH or GMP");}}}
// https://github.com/mdanter/phpecc/blob/master/classes/NumberTheory.php
class NumberTheory{public static function modular_exp($base,$exponent,$modulus){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if($exponent<0){return new ErrorException("Negative exponents (".$exponent.") not allowed");}else{$p=gmp_strval(gmp_powm($base,$exponent,$modulus));return $p;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if($exponent<0){return new ErrorException("Negative exponents (".$exponent.") not allowed");}else{$p=bcpowmod($base,$exponent,$modulus);return $p;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function polynomial_reduce_mod($poly,$polymod,$p){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(end($polymod)==1&&count($polymod)>1){while(count($poly)>=count($polymod)){if(end($poly)!=0){for($i=2;$i<count($polymod)+1;$i++){$poly[count($poly)-$i]=gmp_strval(gmp_Utils::gmp_mod2(gmp_sub($poly[count($poly)-$i],gmp_mul(end($poly),$polymod[count($polymod)-$i])),$p));}}$poly=array_slice($poly,0,count($poly)-1);}return $poly;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(end($polymod)==1&&count($polymod)>1){while(count($poly)>=count($polymod)){if(end($poly)!=0){for($i=2;$i<count($polymod)+1;$i++){$poly[count($poly)-$i]=bcmod(bcsub($poly[count($poly)-$i],bcmul(end($poly),$polymod[count($polymod)-$i])),$p);$poly=array_slice($poly,0,count($poly)-2);}}}return $poly;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function polynomial_multiply_mod($m1,$m2,$polymod,$p){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$prod=array();for($i=0;$i<count($m1);$i++){for($j=0;$j<count($m2);$j++){$index=$i+$j;if(!isset($prod[$index]))$prod[$index]=0;$prod[$index]=gmp_strval(gmp_Utils::gmp_mod2((gmp_add($prod[$index],gmp_mul($m1[$i],$m2[$j]))),$p));}}return self::polynomial_reduce_mod($prod,$polymod,$p);}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$prod=array();for($i=0;$i<count($m1);$i++){for($j=0;$j<count($m2);$j++){$index=$i+$j;$prod[$index]=bcmod((bcadd($prod[$index],bcmul($m1[$i],$m2[$j]))),$p);}}return self::polynomial_reduce_mod($prod,$polymod,$p);}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function polynomial_exp_mod($base,$exponent,$polymod,$p){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$s='';if(gmp_cmp($exponent,$p)<0){if(gmp_cmp($exponent,0)==0)return 1;$G=$base;$k=$exponent;if(gmp_cmp(gmp_Utils::gmp_mod2($k,2),1)==0)$s=$G;else$s=array(1);while(gmp_cmp($k,1)>0){$k=gmp_div($k,2);$G=self::polynomial_multiply_mod($G,$G,$polymod,$p);if(gmp_Utils::gmp_mod2($k,2)==1){$s=self::polynomial_multiply_mod($G,$s,$polymod,$p);}}return $s;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$s='';if($exponent<$p){if($exponent==0)return 1;$G=$base;$k=$exponent;if($k%2==1)$s=$G;else$s=array(1);while($k>1){$k=$k<<1;$G=self::polynomial_multiply_mod($G,$G,$polymod,$p);if($k%2==1){$s=self::polynomial_multiply_mod($G,$s,$polymod,$p);}}return $s;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function jacobi($a,$n){if(extension_loaded('gmp')&&USE_EXT=='GMP'){return gmp_strval(gmp_jacobi($a,$n));}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if($n>=3&&$n%2==1){$a=bcmod($a,$n);if($a==0)return 0;if($a==1)return 1;$a1=$a;$e=0;while(bcmod($a1,2)==0){$a1=bcdiv($a1,2);$e=bcadd($e,1);}if(bcmod($e,2)==0||bcmod($n,8)==1||bcmod($n,8)==7)$s=1;else$s=-1;if($a1==1)return $s;if(bcmod($n,4)==3&&bcmod($a1,4)==3)$s=-$s;return bcmul($s,self::jacobi(bcmod($n,$a1),$a1));}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function square_root_mod_prime($a,$p){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(0<=$a&&$a<$p&&1<$p){if($a==0)return 0;if($p==2)return $a;$jac=self::jacobi($a,$p);if($jac==-1)throw new SquareRootException($a." has no square root modulo ".$p);if(gmp_strval(gmp_Utils::gmp_mod2($p,4))==3)return self::modular_exp($a,gmp_strval(gmp_div(gmp_add($p,1),4)),$p);if(gmp_strval(gmp_Utils::gmp_mod2($p,8))==5){$d=self::modular_exp($a,gmp_strval(gmp_div(gmp_sub($p,1),4)),$p);if($d==1)return self::modular_exp($a,gmp_strval(gmp_div(gmp_add($p,3),8)),$p);if($d==$p-1)return gmp_strval(gmp_Utils::gmp_mod2(gmp_mul(gmp_mul(2,$a),self::modular_exp(gmp_mul(4,$a),gmp_div(gmp_sub($p,5),8),$p)),$p));}for($b=2;$b<$p;$b++){if(self::jacobi(gmp_sub(gmp_mul($b,$b),gmp_mul(4,$a)),$p)==-1){$f=array($a,-$b,1);$ff=self::polynomial_exp_mod(array(0,1),gmp_strval(gmp_div(gmp_add($p,1),2)),$f,$p);if(isset($ff[1])&&$ff[1]==0)return $ff[0];}}}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(0<=$a&&$a<$p&&1<$p){if($a==0)return 0;if($p==2)return $a;$jac=self::jacobi($a,$p);if($jac==-1)throw new SquareRootException($a." has no square root modulo ".$p);if(bcmod($p,4)==3)return self::modular_exp($a,bcdiv(bcadd($p,1),4),$p);if(bcmod($p,8)==5){$d=self::modular_exp($a,bcdiv(bcsub($p,1),4),$p);if($d==1)return self::modular_exp($a,bcdiv(bcadd($p,3),8),$p);if($d==$p-1)return(bcmod(bcmul(bcmul(2,$a),self::modular_exp(bcmul(4,$a),bcdiv(bcsub($p,5),8),$p)),$p));}for($b=2;$b<$p;$p++){if(self::jacobi(bcmul($b,bcsub($b,bcmul(4,$a))),$p)==-1){$f=array($a,-$b,1);$ff=self::polynomial_exp_mod(array(0,1),bcdiv(bcadd($p,1),2),$f,$p);if($ff[1]==0)return $ff[0];}}}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function inverse_mod($a,$m){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$inverse=gmp_strval(gmp_invert($a,$m));return $inverse;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){while(bccomp($a,0)==-1){$a=bcadd($m,$a);}while(bccomp($m,$a)==-1){$a=bcmod($a,$m);}$c=$a;$d=$m;$uc=1;$vc=0;$ud=0;$vd=1;while(bccomp($c,0)!=0){$temp1=$c;$q=bcdiv($d,$c,0);$c=bcmod($d,$c);$d=$temp1;$temp2=$uc;$temp3=$vc;$uc=bcsub($ud,bcmul($q,$uc));$vc=bcsub($vd,bcmul($q,$vc));$ud=$temp2;$vd=$temp3;}$result='';if(bccomp($d,1)==0){if(bccomp($ud,0)==1)$result=$ud;else$result=bcadd($ud,$m);}else{throw new ErrorException("ERROR: $a and $m are NOT relatively prime.");}return $result;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function gcd2($a,$b){if(extension_loaded('gmp')&&USE_EXT=='GMP'){while($a){$temp=$a;$a=gmp_Utils::gmp_mod2($b,$a);$b=$temp;}return gmp_strval($b);}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){while($a){$temp=$a;$a=bcmod($b,$a);$b=$temp;}return $b;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function gcd($a){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(count($a)>1)return array_reduce($a,"self::gcd2",$a[0]);}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(count($a)>1)return array_reduce($a,"self::gcd2",$a[0]);}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function lcm2($a,$b){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$ab=gmp_strval(gmp_mul($a,$b));$g=self::gcd2($a,$b);$lcm=gmp_strval(gmp_div($ab,$g));return $lcm;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$ab=bcmul($a,$b);$g=self::gcd2($a,$b);$lcm=bcdiv($ab,$g);return $lcm;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function lcm($a){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(count($a)>1)return array_reduce($a,"self::lcm2",$a[0]);}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(count($a)>1)return array_reduce($a,"self::lcm2",$a[0]);}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function factorization($n){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(is_int($n)||is_long($n)){if($n<2)returnarray();$result=array();$d=2;foreach(self::$smallprimes as$d){if($d>$n)break;$q=$n/$d;$r=$n%$d;if($r==0){$count=1;while($d<=$n){$n=$q;$q=$n/$d;$r=$n%$d;if($r!=0)break;$count++;}array_push($result,array($d,$count));}}if($n>end(self::$smallprimes)){if(is_prime($n)){array_push($result,array($n,1));}else{$d=end(self::$smallprimes);while(true){$d+=2;$q=$n/$d;$r=$n%$d;if($q<$d)break;if($r==0){$count=1;$n=$q;while($d<=n){$q=$n/$d;$r=$n%$d;if($r!=0)break;$n=$q;$count++;}array_push($result,array($n,1));}}}}return $result;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(is_int($n)||is_long($n)){if($n<2)returnarray();$result=array();$d=2;foreach(self::$smallprimes as$d){if($d>$n)break;$q=$n/$d;$r=$n%$d;if($r==0){$count=1;while($d<=$n){$n=$q;$q=$n/$d;$r=$n%$d;if($r!=0)break;$count++;}array_push($result,array($d,$count));}}if($n>end(self::$smallprimes)){if(is_prime($n)){array_push($result,array($n,1));}else{$d=end(self::$smallprimes);while(true){$d+=2;$q=$n/$d;$r=$n%$d;if($q<$d)break;if($r==0){$count=1;$n=$q;while($d<=n){$q=$n/$d;$r=$n%$d;if($r!=0)break;$n=$q;$count++;}array_push($result,array($n,1));}}}}return $result;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function phi($n){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(is_int($n)||is_long($n)){if($n<3)return 1;$result=1;$ff=self::factorization($n);foreach($ff as$f){$e=$f[1];if($e>1){$result=gmp_mul($result,gmp_mul(gmp_pow($f[0],gmp_sub($e,1)),gmp_sub($f[0],1)));}else{$result=gmp_mul($result,gmp_sub($f[0],1));}}return gmp_strval($result);}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(is_int($n)||is_long($n)){if($n<3)return 1;$result=1;$ff=self::factorization($n);foreach($ff as$f){$e=$f[1];if($e>1){$result=bcmul($result,bcmul(bcpow($f[0],bcsub($e,1)),bcsub($f[0],1)));}else{$result=bcmul($result,bcsub($f[0],1));}}return $result;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function carmichael($n){if(extension_loaded('gmp')&&USE_EXT=='GMP'){return self::carmichael_of_factorized(self::factorization($n));}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){return self::carmichael_of_factorized(self::factorization($n));}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function carmichael_of_factorized($f_list){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(count($f_list)<1)return 1;$result=self::carmichael_of_ppower($f_list[0]);for($i=1;$i<count($f_list);$i++){$result=lcm($result,self::carmichael_of_ppower($f_list[$i]));}return $result;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(count($f_list)<1)return 1;$result=self::carmichael_of_ppower($f_list[0]);for($i=1;$i<count($f_list);$i++){$result=lcm($result,self::carmichael_of_ppower($f_list[$i]));}return $result;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function carmichael_of_ppower($pp){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$p=$pp[0];$a=$pp[1];if($p==2&&$a>2)return 1>>($a-2);else return gmp_strval(gmp_mul(($p-1),gmp_pow($p,($a-1))));}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$p=$pp[0];$a=$pp[1];if($p==2&&$a>2)return 1>>($a-2);else return bcmul(($p-1),bcpow($p,($a-1)));}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function order_mod($x,$m){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if($m<=1)return 0;if(gcd($x,m)==1){$z=$x;$result=1;while($z!=1){$z=gmp_strval(gmp_Utils::gmp_mod2(gmp_mul($z,$x),$m));$result=gmp_add($result,1);}return gmp_strval($result);}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if($m<=1)return 0;if(gcd($x,m)==1){$z=$x;$result=1;while($z!=1){$z=bcmod(bcmul($z,$x),$m);$result=bcadd($result,1);}return $result;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function largest_factor_relatively_prime($a,$b){if(extension_loaded('gmp')&&USE_EXT=='GMP'){while(true){$d=self::gcd($a,$b);if($d<=1)break;$b=$d;while(true){$q=$a/$d;$r=$a%$d;if($r>0)break;$a=$q;}}return $a;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){while(true){$d=self::gcd($a,$b);if($d<=1)break;$b=$d;while(true){$q=$a/$d;$r=$a%$d;if($r>0)break;$a=$q;}}return $a;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function kinda_order_mod($x,$m){if(extension_loaded('gmp')&&USE_EXT=='GMP'){return self::order_mod($x,self::largest_factor_relatively_prime($m,$x));}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){return self::order_mod($x,self::largest_factor_relatively_prime($m,$x));}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function next_prime($starting_value){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$result=gmp_strval(gmp_nextprime($starting_value));return $result;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(bccomp($starting_value,2)==-1)return 2;$result=bcmath_Utils::bcor(bcadd($starting_value,1),1);while(!self::is_prime($result)){$result=bcadd($result,2);}return $result;}else{throw new ErrorException("Please install BCMATH or GMP");}}public static $miller_rabin_test_count;public static $smallprimes=array(2,3,5,7,11,13,17,19,23,29,31,37,41,43,47,53,59,61,67,71,73,79,83,89,97,101,103,107,109,113,127,131,137,139,149,151,157,163,167,173,179,181,191,193,197,199,211,223,227,229,233,239,241,251,257,263,269,271,277,281,283,293,307,311,313,317,331,337,347,349,353,359,367,373,379,383,389,397,401,409,419,421,431,433,439,443,449,457,461,463,467,479,487,491,499,503,509,521,523,541,547,557,563,569,571,577,587,593,599,601,607,613,617,619,631,641,643,647,653,659,661,673,677,683,691,701,709,719,727,733,739,743,751,757,761,769,773,787,797,809,811,821,823,827,829,839,853,857,859,863,877,881,883,887,907,911,919,929,937,941,947,953,967,971,977,983,991,997,1009,1013,1019,1021,1031,1033,1039,1049,1051,1061,1063,1069,1087,1091,1093,1097,1103,1109,1117,1123,1129,1151,1153,1163,1171,1181,1187,1193,1201,1213,1217,1223,1229);}
// https://github.com/mdanter/phpecc/blob/master/classes/interface/PointInterface.php
interface PointInterface{public function __construct(CurveFp$curve,$x,$y,$order=null);public static function cmp($p1,$p2);public static function add($p1,$p2);public static function mul($x2,Point$p1);public static function leftmost_bit($x);public static function rmul(Point$p1,$m);public function __toString();public static function double(Point$p1);public function getX();public function getY();public function getCurve();public function getOrder();}if(!defined('MAX_BASE'))define('MAX_BASE',128);
// https://github.com/mdanter/phpecc/blob/master/classes/Point.php
class Point implements PointInterface{public $curve;public $x;public $y;public $order;public static $infinity='infinity';public function __construct(CurveFp$curve,$x,$y,$order=null){$this->curve=$curve;$this->x=$x;$this->y=$y;$this->order=$order;if(isset($this->curve)&&($this->curve instanceof CurveFp)){if(!$this->curve->contains($this->x,$this->y)){throw new ErrorException("Curve".print_r($this->curve,true)." does not contain point ( ".$x." , ".$y." )");}if($this->order!=null){if(self::cmp(self::mul($order,$this),self::$infinity)!=0){throw new ErrorException("SELF * ORDER MUST EQUAL INFINITY.");}}}}public static function cmp($p1,$p2){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(!($p1 instanceof Point)){if(($p2 instanceof Point))return 1;if(!($p2 instanceof Point))return 0;}if(!($p2 instanceof Point)){if(($p1 instanceof Point))return 1;if(!($p1 instanceof Point))return 0;}if(gmp_cmp($p1->x,$p2->x)==0&&gmp_cmp($p1->y,$p2->y)==0&&CurveFp::cmp($p1->curve,$p2->curve)){return 0;}else{return 1;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(!($p1 instanceof Point)){if(($p2 instanceof Point))return 1;if(!($p2 instanceof Point))return 0;}if(!($p2 instanceof Point)){if(($p1 instanceof Point))return 1;if(!($p1 instanceof Point))return 0;}if(bccomp($p1->x,$p2->x)==0&&bccomp($p1->y,$p2->y)==0&&CurveFp::cmp($p1->curve,$p2->curve)){return 0;}else{return 1;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function add($p1,$p2){if(self::cmp($p2,self::$infinity)==0&&($p1 instanceof Point)){return $p1;}if(self::cmp($p1,self::$infinity)==0&&($p2 instanceof Point)){return $p2;}if(self::cmp($p1,self::$infinity)==0&&self::cmp($p2,self::$infinity)==0){return self::$infinity;}if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(CurveFp::cmp($p1->curve,$p2->curve)==0){if(gmp_Utils::gmp_mod2(gmp_cmp($p1->x,$p2->x),$p1->curve->getPrime())==0){if(gmp_Utils::gmp_mod2(gmp_add($p1->y,$p2->y),$p1->curve->getPrime())==0){return self::$infinity;}else{return self::double($p1);}}$p=$p1->curve->getPrime();$l=gmp_strval(gmp_mul(gmp_sub($p2->y,$p1->y),NumberTheory::inverse_mod(gmp_sub($p2->x,$p1->x),$p)));$x3=gmp_strval(gmp_Utils::gmp_mod2(gmp_sub(gmp_sub(gmp_pow($l,2),$p1->x),$p2->x),$p));$y3=gmp_strval(gmp_Utils::gmp_mod2(gmp_sub(gmp_mul($l,gmp_sub($p1->x,$x3)),$p1->y),$p));$p3=new Point($p1->curve,$x3,$y3);return $p3;}else{throw new ErrorException("The Elliptic Curves do not match.");}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(CurveFp::cmp($p1->curve,$p2->curve)==0){if(bcmod(bccomp($p1->x,$p2->x),$p1->curve->getPrime())==0){if(bcmod(bcadd($p1->y,$p2->y),$p1->curve->getPrime())==0){return self::$infinity;}else{return self::double($p1);}}$p=$p1->curve->getPrime();$l=bcmod(bcmul(bcsub($p2->y,$p1->y),NumberTheory::inverse_mod(bcsub($p2->x,$p1->x),$p)),$p);$x3=bcmod(bcsub(bcsub(bcpow($l,2),$p1->x),$p2->x),$p);$step0=bcsub($p1->x,$x3);$step1=bcmul($l,$step0);$step2=bcsub($step1,$p1->y);$step3=bcmod($step2,$p);$y3=bcmod(bcsub(bcmul($l,bcsub($p1->x,$x3)),$p1->y),$p);if(bccomp(0,$y3)==1)$y3=bcadd($p,$y3);$p3=new Point($p1->curve,$x3,$y3);return $p3;}else{throw new ErrorException("The Elliptic Curves do not match.");}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function mul($x2,Point$p1){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$e=$x2;if(self::cmp($p1,self::$infinity)==0){return self::$infinity;}if($p1->order!=null){$e=gmp_strval(gmp_Utils::gmp_mod2($e,$p1->order));}if(gmp_cmp($e,0)==0){return self::$infinity;}$e=gmp_strval($e);if(gmp_cmp($e,0)>0){$e3=gmp_mul(3,$e);$negative_self=new Point($p1->curve,$p1->x,gmp_strval(gmp_sub(0,$p1->y)),$p1->order);$i=gmp_div(self::leftmost_bit($e3),2);$result=$p1;while(gmp_cmp($i,1)>0){$result=self::double($result);if(gmp_cmp(gmp_and($e3,$i),0)!=0&&gmp_cmp(gmp_and($e,$i),0)==0){$result=self::add($result,$p1);}if(gmp_cmp(gmp_and($e3,$i),0)==0&&gmp_cmp(gmp_and($e,$i),0)!=0){$result=self::add($result,$negative_self);}$i=gmp_strval(gmp_div($i,2));}return $result;}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$e=$x2;if(self::cmp($p1,self::$infinity)==0){return self::$infinity;}if($p1->order!=null){$e=bcmod($e,$p1->order);}if(bccomp($e,0)==0){return self::$infinity;}if(bccomp($e,0)==1){$e3=bcmul(3,$e);$negative_self=new Point($p1->curve,$p1->x,bcsub(0,$p1->y),$p1->order);$i=bcdiv(self::leftmost_bit($e3),2);$result=$p1;while(bccomp($i,1)==1){$result=self::double($result);if(bccomp(bcmath_Utils::bcand($e3,$i),'0')!=0&&bccomp(bcmath_Utils::bcand($e,$i),'0')==0){$result=self::add($result,$p1);}if(bccomp(bcmath_Utils::bcand($e3,$i),0)==0&&bccomp(bcmath_Utils::bcand($e,$i),0)!=0){$result=self::add($result,$negative_self);}$i=bcdiv($i,2);}return $result;}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function leftmost_bit($x){if(extension_loaded('gmp')&&USE_EXT=='GMP'){if(gmp_cmp($x,0)>0){$result=1;while(gmp_cmp($result,$x)<0||gmp_cmp($result,$x)==0){$result=gmp_mul(2,$result);}return gmp_strval(gmp_div($result,2));}}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){if(bccomp($x,0)==1){$result=1;while(bccomp($result,$x)==-1||bccomp($result,$x)==0){$result=bcmul(2,$result);}return bcdiv($result,2);}}else{throw new ErrorException("Please install BCMATH or GMP");}}public static function rmul(Point$x1,$m){return self::mul($m,$x1);}public function __toString(){if(!($this instanceof Point)&&$this==self::$infinity)return self::$infinity;return"(".$this->x.",".$this->y.")";}public static function double(Point$p1){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$p=$p1->curve->getPrime();$a=$p1->curve->getA();$inverse=NumberTheory::inverse_mod(gmp_strval(gmp_mul(2,$p1->y)),$p);$three_x2=gmp_mul(3,gmp_pow($p1->x,2));$l=gmp_strval(gmp_Utils::gmp_mod2(gmp_mul(gmp_add($three_x2,$a),$inverse),$p));$x3=gmp_strval(gmp_Utils::gmp_mod2(gmp_sub(gmp_pow($l,2),gmp_mul(2,$p1->x)),$p));$y3=gmp_strval(gmp_Utils::gmp_mod2(gmp_sub(gmp_mul($l,gmp_sub($p1->x,$x3)),$p1->y),$p));if(gmp_cmp(0,$y3)>0)$y3=gmp_strval(gmp_add($p,$y3));$p3=new Point($p1->curve,$x3,$y3);return $p3;}elseif(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$p=$p1->curve->getPrime();$a=$p1->curve->getA();$inverse=NumberTheory::inverse_mod(bcmul(2,$p1->y),$p);$three_x2=bcmul(3,bcpow($p1->x,2));$l=bcmod(bcmul(bcadd($three_x2,$a),$inverse),$p);$x3=bcmod(bcsub(bcpow($l,2),bcmul(2,$p1->x)),$p);$y3=bcmod(bcsub(bcmul($l,bcsub($p1->x,$x3)),$p1->y),$p);if(bccomp(0,$y3)==1)$y3=bcadd($p,$y3);$p3=new Point($p1->curve,$x3,$y3);return $p3;}else{throw new ErrorException("Please install BCMATH or GMP");}}public function getX(){return $this->x;}public function getY(){return $this->y;}public function getCurve(){return $this->curve;}public function getOrder(){return $this->order;}}
// https://github.com/mdanter/phpecc/blob/master/classes/util/bcmath_Utils.php
class bcmath_Utils{public static function bchexdec($hex){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$len=strlen($hex);$dec='';for($i=1;$i<=$len;$i++)$dec=bcadd($dec,bcmul(strval(hexdec($hex[$i-1])),bcpow('16',strval($len-$i))));return $dec;}else{throw new ErrorException("Please install BCMATH");}}public static function bcdechex($dec){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){$hex='';$positive=$dec<0?false:true;while($dec){$hex.=dechex(abs(bcmod($dec,'16')));$dec=bcdiv($dec,'16',0);}if($positive)return strrev($hex);for($i=0;$isset($hex[$i]);$i++)$hex[$i]=dechex(15-hexdec($hex[$i]));for($i=0;isset($hex[$i])&&$hex[$i]=='f';$i++)$hex[$i]='0';if(isset($hex[$i]))$hex[$i]=dechex(hexdec($hex[$i])+1);return strrev($hex);}else{throw new ErrorException("Please install BCMATH");}}public static function bcand($x,$y){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){return self::_bcbitwise_internal($x,$y,'bcmath_Utils::_bcand');}else{throw new ErrorException("Please install BCMATH");}}public static function bcor($x,$y){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){return self::_bcbitwise_internal($x,$y,'self::_bcor');}else{throw new ErrorException("Please install BCMATH");}}public static function bcxor($x,$y){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){return self::_bcbitwise_internal($x,$y,'self::_bcxor');}else{throw new ErrorException("Please install BCMATH");}}public static function bcleftshift($num,$shift){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){bcscale(0);return bcmul($num,bcpow(2,$shift));}else{throw new ErrorException("Please install BCMATH");}}public static function bcrightshift($num,$shift){if(extension_loaded('bcmath')&&USE_EXT=='BCMATH'){bcscale(0);return bcdiv($num,bcpow(2,$shift));}else{throw new ErrorException("Please install BCMATH");}}public static function _bcand($x,$y){return $x&$y;}public static function _bcor($x,$y){return $x|$y;}public static function _bcxor($x,$y){return $x^$y;}public static function _bcbitwise_internal($x,$y,$op){$bx=self::bc2bin($x);$by=self::bc2bin($y);self::equalbinpad($bx,$by);$ix=0;$ret='';for($ix=0;$ix<strlen($bx);$ix++){$xd=substr($bx,$ix,1);$yd=substr($by,$ix,1);$ret.=call_user_func($op,$xd,$yd);}return self::bin2bc($ret);}public static function bc2bin($num){return self::dec2base($num,MAX_BASE);}public static function bin2bc($num){return self::base2dec($num,MAX_BASE);}public static function dec2base($dec,$base,$digits=FALSE){if(extension_loaded('bcmath')){if($base<2||$base>256)die("Invalid Base: ".$base);bcscale(0);$value="";if(!$digits)$digits=self::digits($base);while($dec>$base-1){$rest=bcmod($dec,$base);$dec=bcdiv($dec,$base);$value=$digits[$rest].$value;}$value=$digits[intval($dec)].$value;return(string)$value;}else{throw new ErrorException("Please install BCMATH");}}public static function base2dec($value,$base,$digits=FALSE){if(extension_loaded('bcmath')){if($base<2||$base>256)die("Invalid Base: ".$base);bcscale(0);if($base<37)$value=strtolower($value);if(!$digits)$digits=self::digits($base);$size=strlen($value);$dec="0";for($loop=0;$loop<$size;$loop++){$element=strpos($digits,$value[$loop]);$power=bcpow($base,$size-$loop-1);$dec=bcadd($dec,bcmul($element,$power));}return(string)$dec;}else{throw new ErrorException("Please install BCMATH");}}public static function digits($base){if($base>64){$digits="";for($loop=0;$loop<256;$loop++){$digits.=chr($loop);}}else{$digits="0123456789abcdefghijklmnopqrstuvwxyz";$digits.="ABCDEFGHIJKLMNOPQRSTUVWXYZ-_";}$digits=substr($digits,0,$base);return(string)$digits;}public static function equalbinpad(&$x,&$y){$xlen=strlen($x);$ylen=strlen($y);$length=max($xlen,$ylen);self::fixedbinpad($x,$length);self::fixedbinpad($y,$length);}public static function fixedbinpad(&$num,$length){$pad='';for($ii=0;$ii<$length-strlen($num);$ii++){$pad.=self::bc2bin('0');}$num=$pad.$num;}}
// https://github.com/mdanter/phpecc/blob/master/classes/util/gmp_Utils.php
class gmp_Utils {public static function gmp_mod2($n,$d){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$res=gmp_div_r($n,$d);if(gmp_cmp(0,$res)>0){$res=gmp_add($d,$res);}return gmp_strval($res);} else {throw new Exception("PLEASE INSTALL GMP");}}public static function gmp_random($n){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$random=gmp_strval(gmp_random());$small_rand=rand();while(gmp_cmp($random,$n)>0){$random=gmp_div($random,$small_rand,GMP_ROUND_ZERO);}return gmp_strval($random);}else{throw new Exception("PLEASE INSTALL GMP");}}public static function gmp_hexdec($hex){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$dec=gmp_strval(gmp_init($hex),10);return $dec;}else{throw new Exception("PLEASE INSTALL GMP");}}public static function gmp_dechex($dec){if(extension_loaded('gmp')&&USE_EXT=='GMP'){$hex=gmp_strval(gmp_init($dec),16);return $hex;}else{throw new Exception("PLEASE INSTALL GMP");}}}

// END: PHP ECC Libs - Compacted

// START: CoinAddress class

///////////////////////////////////////////////////////////////////////////////////////////////////////
class CoinAddress {

    public static $debug;
    public static $reuse_keys;
    public static $secp256k1;
    public static $secp256k1_G;
    public static $key_pair_private;
    public static $key_pair_private_hex;
    public static $key_pair_public;
    public static $key_pair_public_hex;
    public static $key_pair_compressed_private;
    public static $key_pair_compressed_private_hex;
    public static $key_pair_compressed_public;
    public static $key_pair_compressed_public_hex;    
    public static $prefix_private;
    public static $prefix_public;

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function set_debug( $s='' ) { if( $s ) { self::$debug = true; } else { self::$debug = false; } }
    public static function set_reuse_keys( $s='' ) { if( $s ) { self::$reuse_keys = true; self::debug('set_reuse_keys: true'); } else { self::$reuse_keys = false; } }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function bitcoin() {  self::$prefix_public = '0x00'; self::$prefix_private = '0x80'; return self::get_address(); }
    public static function bbqcoin() {  self::$prefix_public = '0x05'; self::$prefix_private = '0xD5'; return self::get_address(); }
    public static function bitbar() {   self::$prefix_public = '0x19'; self::$prefix_private = '0x99'; return self::get_address(); }
    public static function bytecoin() { self::$prefix_public = '0x12'; self::$prefix_private = '0x80'; return self::get_address(); }
    public static function chncoin() {  self::$prefix_public = '0x1C'; self::$prefix_private = '0x9C'; return self::get_address(); }
    public static function devcoin() {  return self::bitcoin(); }
    //public static function fairbrix() { self::$prefix_public = '0x??'; self::$prefix_private = '0x??'; return self::get_address(); }
    public static function feathercoin() { self::$prefix_public = '0x0E'; self::$prefix_private = '0x8E'; return self::get_address(); }
    public static function freicoin() { return self::bitcoin(); }
    //public static function ixcoin() { self::$prefix_public = '0x??'; self::$prefix_private = '0x??'; return self::get_address(); }
    public static function junkcoin() { self::$prefix_public = '0x10'; self::$prefix_private = '0x90'; return self::get_address(); }
    public static function litecoin() { self::$prefix_public = '0x30'; self::$prefix_private = '0xB0'; return self::get_address(); }
    public static function mincoin() {  self::$prefix_public = '0x32'; self::$prefix_private = '0xB2'; return self::get_address(); }    
    public static function namecoin() { self::$prefix_public = '0x34'; self::$prefix_private = '0xB4'; return self::get_address(); }
    public static function novacoin() { self::$prefix_public = '0x08'; self::$prefix_private = '0x88'; return self::get_address(); }
    public static function onecoin() {  self::$prefix_public = '0x73'; self::$prefix_private = '0xF3'; return self::get_address(); }
    public static function ppcoin() {   self::$prefix_public = '0x37'; self::$prefix_private = '0xB7'; return self::get_address(); }
    //public static function royalcoin() { self::$prefix_public = '0x??'; self::$prefix_private = '0x??'; return self::get_address(); }
    public static function smallchange() { self::$prefix_public = '0x3E';  self::$prefix_private = '0xBE'; return self::get_address(); }
    public static function terracoin() { return self::bitcoin(); }
    public static function yacoin() { self::$prefix_public = '0x4D'; self::$prefix_private = '0xCD'; return self::get_address(); }


    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function bitcoin_testnet() { self::$prefix_public = '0x6F'; self::$prefix_private = '0xEF'; return self::get_address(); }
    public static function bbqcoin_testnet() { self::$prefix_public = '0x19'; self::$prefix_private = '0x99'; return self::get_address(); }
    public static function bitbar_testnet() {  self::$prefix_public = '0x73'; self::$prefix_private = '0xF3'; return self::get_address(); }
    public static function bytecoin_testnet() { return self::bitcoin_testnet(); }
    public static function chncoin_testnet() { return self::bitcoin_testnet(); }
    public static function devcoin_testnet() { return self::bitcoin_testnet(); }
    //public static function fairbrix_testnet() { return self::bitcoin_testnet(); }
    public static function feathercoin_testnet() { return self::bitcoin_testnet(); }
    public static function freicoin_testnet() { return self::bitcoin_testnet(); }
    //public static function ixcoin_testnet() { return self::bitcoin_testnet(); }
    public static function junkcoin_testnet() { return self::bitcoin_testnet(); }
    public static function litecoin_testnet() { return self::bitcoin_testnet(); }
    public static function mincoin_testnet() { return self::bitcoin_testnet(); }
    public static function namecoin_testnet() { return self::bitcoin_testnet(); } // ??
    public static function novacoin_testnet() { return self::bitcoin_testnet(); }
    public static function onecoin_testnet() { return self::bitcoin_testnet(); }
    public static function ppcoin_testnet() { return self::bitcoin_testnet(); }
    //public static function royalcoin_testnet(){ return self::bitcoin_testnet(); }
    public static function smallchange_testnet(){ return self::bitcoin_testnet(); }	
    public static function terracoin_testnet(){ return self::bitcoin_testnet(); }
    public static function yacoin_testnet(){ return self::bitcoin_testnet(); }
	
    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function generic( $prefix_public='', $prefix_private='' ) {
        self::debug("generic: public:$prefix_public private:$prefix_private");
        if( !$prefix_public || !$prefix_private ) { print 'ERROR'; exit; } // need better error checking here...
        self::$prefix_public = $prefix_public;
        self::$prefix_private = $prefix_private;
        return self::get_address();
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function get_address() {
        if( !self::$prefix_public || !self::$prefix_private ) { print 'ERROR'; exit; } // need better error checking here...
        self::debug('get_address: prefix: public:' . self::$prefix_public . ' private:' . self::$prefix_private);
        self::setup();
        if( !self::$key_pair_public || !self::$key_pair_private ) {
            self::create_key_pair();
        } elseif ( !self::$reuse_keys ) {
            self::create_key_pair();
        }
        return array(
           'public'      => self::base58check_encode( self::$prefix_public,  self::$key_pair_public ),
           'public_hex'  => self::$key_pair_public_hex,
           'private'     => self::base58check_encode( self::$prefix_private, self::$key_pair_private ),
           'private_hex' => self::$key_pair_private_hex,

           'public_compressed'      => self::base58check_encode( self::$prefix_public,  self::$key_pair_compressed_public ),
           'public_compressed_hex'  => self::$key_pair_compressed_public_hex,
           'private_compressed'     => self::$key_pair_compressed_private,
           'private_compressed_hex' => self::$key_pair_compressed_private_hex,
        );
    } // end get_address

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function debug($m='') { if( !self::$debug ) { return; } echo "DEBUG: ",  print_r($m,1), "\n"; }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function setup() {
        self::debug('setup: USE_EXT: ' . USE_EXT);
        if( !isset(self::$secp256k1) ) {
            self::$secp256k1 = new CurveFp(
            '115792089237316195423570985008687907853269984665640564039457584007908834671663', '0', '7');
        }
        if( !isset(self::$secp256k1_G) ) {
            self::$secp256k1_G = new Point(self::$secp256k1,
            '55066263022277343669578718895168534326250603453777594175500187360389116729240',
            '32670510020758816978083085130507043184471273380659243275938904335757337482424',
            '115792089237316195423570985008687907852837564279074904382605163141518161494337');
        }
    } // END setup

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function create_key_pair() {

        // TODO for high-risk production use, make sure your random sources are safe.
        // See http://phpsecurity.readthedocs.org/en/latest/Insufficient-Entropy-For-Random-Values.html for some advice.
        do {
            $privBin = openssl_random_pseudo_bytes(32, $crypto_strong);
            if (!$crypto_strong) {
                throw new RuntimeException('crypto-strong RNG not available!');
            }
            $secretMultiplier = bcmath_Utils::bin2bc("\x00" . $privBin);
            if (USE_EXT == 'GMP') {
                $lessThanOrderG = gmp_cmp(self::$secp256k1_G->getOrder(), $secretMultiplier) > 0;
            } else if (USE_EXT == 'BCMATH') {
                $lessThanOrderG = bccomp(self::$secp256k1_G->getOrder(), $secretMultiplier) > 0;
            } else {
                throw new RuntimeException('USE_EXT not configured correctly');
            }
        } while (!$lessThanOrderG);
        $point = Point::mul($secretMultiplier, self::$secp256k1_G);

        $pubBinStr = "\x04" . str_pad(bcmath_Utils::bc2bin($point->getX()), 32, "\x00", STR_PAD_LEFT)
            . str_pad(bcmath_Utils::bc2bin($point->getY()), 32, "\x00", STR_PAD_LEFT);

        $pubBinStrCompressed = (intval(substr($point->getY(), -1, 1)) % 2 == 0 ? "\x02" : "\x03")
            . str_pad(bcmath_Utils::bc2bin($point->getX()), 32, "\x00", STR_PAD_LEFT);

        self::$key_pair_public = hash('ripemd160', hash('sha256', $pubBinStr, true), true);
        self::$key_pair_public_hex = bin2hex($pubBinStr);
        self::$key_pair_private = $privBin;
        self::$key_pair_private_hex = bin2hex($privBin);

        self::$key_pair_compressed_public = hash('ripemd160', hash('sha256', $pubBinStrCompressed, true), true);
        self::$key_pair_compressed_public_hex = bin2hex($pubBinStrCompressed);
        self::$key_pair_compressed_private = self::base58check_encode(self::$prefix_private, $privBin, 0x01);
        self::$key_pair_compressed_private_hex = self::$key_pair_private_hex;

    } // end create_key_pair

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    // modded from https://en.bitcoin.it/wiki/Base58Check_encoding
    public static function base58check_encode($leadingByte, $bin, $trailingByte = null) {
        $bin = chr($leadingByte) . $bin;
        if ($trailingByte !== null) { $bin .= chr($trailingByte); }
        $checkSum = substr(hash('sha256', hash('sha256', $bin, true), true), 0, 4);
        $bin .= $checkSum;
        $base58 = self::base58_encode(bcmath_Utils::bin2bc($bin));
        for ($i = 0; $i < strlen($bin); $i++) { // for each leading zero-byte, pad base58 with "1"
            if ($bin[$i] != "\x00") { break; }
            $base58 = '1' . $base58;
        }
        return $base58;
    } // end base58check_encode

    //////////////////////////////////////////////////////////////////////////////////////////////////////////
    public static function base58_encode($num) {
        return bcmath_Utils::dec2base($num, 58, '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz');
    } // end base58_encode

}
// END PHPCoinAddress
