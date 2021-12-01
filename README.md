[![GitHub license](https://img.shields.io/github/license/vandarpay/cashier?style=flat-square)](https://github.com/vandarpay/cashier/blob/master/LICENSE)
[![GitHub Workflow Status](https://img.shields.io/github/workflow/status/vandarpay/cashier/tests?style=flat-square)](https://github.com/vandarpay/cashier/actions/workflows/tests.yml)
[![Packagist Downloads](https://img.shields.io/packagist/dt/vandarpay/cashier?style=flat-square)](https://packagist.org/packages/vandarpay/cashier)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/vandarpay/cashier?style=flat-square)
![Laravel Version Support](https://img.shields.io/badge/Laravel-6.0%2C%207.0%2C%208.0-brightgreen?style=flat-square)

Vandar Cashier is a Laravel package that provides you with a seamless integration with Vandar services. Take a look at
Vandar Documentation for more information on the services we provide.

# Setup
To use Vandar Cashier, you need to install it through Composer first:

```bash
composer require vandarpay/cashier
```

Then, you will need to publish the package's assets and migrate your project's database to add Vandar Cashier's tables:

```php
php artisan vendor:publish --provider="Vandar\\Cashier\\VandarCashierServiceProvider"
php artisan migrate
```

After that, open your User model (located in `app/User.php` or `app/Models/User.php` in most projects) and add
the `Billable` trait:

```php
use Vandar\Cashier\Traits\Billable;
// ...
class User extends Authenticatable
{
    use Billable;
// ...
```

You will need to add the necessary configuration to your `.env` file:

```php
VANDAR_MOBILE=
VANDAR_PASSWORD=
VANDAR_BUSINESS_SLUG=
VANDAR_API_KEY=
```

`VANDAR_MOBILE` and `VANDAR_PASSWORD` are your login credentials to Vandar dashboard, the `VANDAR_BUSINESS_SLUG` is set
by you when you add a business in Vandar and `VANDAR_API_KEY` is obtained through your business dashboard.

# Usage

Currently, Vandar Cashier supports three of Vandar services: **IPG**, **Direct Debit**, and **Settlement**. IPG is the more common method used which provides
you with a link that the user can use to pay for a service. The direct debit service works by requesting access from a
user's bank account and withdrawing from their accounts periodically without a need for user interaction.
## IPG

### Independent

if you're creating a donation form, or you don't really need a logged-in user to make payments, you will need two paths.
The first path is going to be initiating the payment and sending it to payment gateway. The second path (also known as
callback url) will verify the transaction once your user has returned.

```php
use Vandar\Cashier\Models\Payment;
Route::get('/initiate-payment', function(Request $request) {
    // Amounts are in IRR
    // For more values, see Payment or https://vandarpay.github.io/docs/ipg/#step-1
    $payment = Payment::create(['amount' => 10000]);
    return redirect($payment->url);
});
```

### User-Dependent

In a user-dependant scenario, we are assuming that anyone making a payment is already logged-in to your system,
therefore, you can create a payment link for them to redirect them to through their user model:

```php
Route::get('/initiate-payment', function(Request $request){
    $user = auth()->user(); // Added as a separate variable for clarity
    // Amounts are in IRR
    // For more values, see Payment or https://vandarpay.github.io/docs/ipg/#step-1
    $payment = $user->payments()->create(['amount' => 10000]);
    return redirect($payment->url); // See documentation for info on payload and callback
});
```

### Callback URL

Once the transaction finishes (successfully or not), they will be redirect back to the path you defined in callback, you
may define a controller or a route to verify the payment using the `Payment::verify($request)` method:

```php
use Vandar\Cashier\Models\Payment;
Route::get('/callback', function(Request $request){
    if(Payment::verifyFromRequest($request)){
        return 'Success!';
    } 
    else {
        return 'Failed!';
    }
});
```

The verify method automatically updates the transaction status in your database.

Also, for IPG, you're going to need to define a callback url for when users are returning from Vandar to your
application, you can either set the `VANDAR_CALLBACK_URL` environment variable or modify `config/vandar.php`. You will
also need to add the callback URL in your Business Dashboard in Vandar or otherwise it will lead into an error.

## Direct-Debit
When setting up direct-debit, you have two main steps to take.

1. Get user to allow for access to their account (also known as a Mandate)
2. Request a withdrawal from a user's account

### Mandate

A mandate is basically a permission that a customer gives us to access their accounts. in Vandar, a customer is defined
by their phone number and that's considered the primary key when it comes to customers. if you're planning to use
subscription services in your project, you will need a
`mobile_number` column in your users table. This code can be used as a migration that will add such a column:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMobileNumbersColumnToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_number')->nullable();
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
           $table->dropColumn('mobile_number');
        });
    }
}
```
You may also pass the `mobile_number` key in the array passed to authorizeMandate manually. 

You can then create a mandate and redirect the user to the bank for authorizing the mandate:
```php
Route::get('/initiate-mandate', function(){
    $user = auth()->user();
    if(! $user->hasValidMandate()){ // You may use the hasValidMandate method if your design requires each user to have only one active mandate
    return redirect($user->authorizeMandate(['bank_code' => '062', 'count' => 3, 'limit' => '10000', 'expiration_date' => '2021-01-01']));
    }
})
```

You are also going to need a callback url for the user to return to after they're finished with the mandate process,
this path should be set as an absolute url through `VANDAR_MANDATE_CALLBACK_URL` or editing the config file.

You can verify whether the mandate was successfully made and update the mandate accordingly using
the `Mandate::verifyFromRequest` method:

```php
use Vandar\Cashier\Models\Mandate;
Route::get('/mandate-callback', function(Request $request){
    if(Mandate::verifyFromRequest($request)){
        return 'Success!';
    } else {
        return 'Failed!';
    }
})
```

You can also revoke any mandates through the Mandate model's revoke function:

```php
use Vandar\Cashier\Models\Mandate

$mandate = Mandate::find(1);
$mandate->revoke(); // true if mandate was revoked successfully
```
Since the only way for a user to cancel a mandate is through your platform, it is standard to provide a way for users to do so 
in a convenient way.

### Withdrawal
Once the mandate has been created successfully, you may create a withdrawal through the mandate:
```php
$user->mandates()->active()->first()->createWithdrawal(['amount' => 10000, 'is_instant' => true, 'description' => 'Subscription renewal', 'max_retry_count' => 16]);
```
Please note that you may use any method you like to find your current mandate, for example if you have more than one mandate per user, you may use different
queries to find that particular mandate.

When creating a new withdrawal, passing `authorization_id` and `notify_url` is not necessary as Cashier automatically sets these values.

Note: if not provided, Vandar Cashier automatically sets `withdrawal_date` to now. (`date('Y-m-d')`)

if you're not creating an instant withdrawal (`is_instant = false`) you can also cancel the withdrawal before it is run:
```php
$status = $withdrawal->cancel(); // Returns 'CANCELED' on success, any other status (DONE, PENDING, INIT, FAILED) on failure.
```

## Settlement
Vandar Cashier provides the ability to make requests for settlements:
```php
$settlement = Settlement::create(['amount' => 5000, 'iban' => 'IR000000000000000000000000']) // amount is in Toman
```
Once a settlement is created successfully, Vandar will queue the settlement and send it to bank. When the settlement is
finalized, a notification will be sent to the URL specified in `settlement_notify_url` in vandar config. 

Vandar Cashier provides a route that automatically handles settlement updates and updates your databases accordingly, so you don't need to 
update the `settlement_notify_url` unless you need to implement your own solution.

You may also cancel a settlement through the cancel method before it is done, if successful, `CANCELED` will be returned.
if the cancel attempt fails, the last known settlement status is returned from the database:
```php
$settlement->cancel(); // Returns `CANCELED` on success.
```


## Utilities
You may use the `Vandar::getBanksHealth()` method to get an array containing a list of all banks and whether they're healthy.

## Error Handling
When making contact with Vandar APIs, a series of exceptions may be raised due to errors while processing your requests in the APIs.
All of these errors can be caught and processed through `Vandar\Exceptions\ResponseException` exception. This means that all exceptions raised
by Cashier have the following methods:
```php
try {
    ...
} catch (\Vandar\Cashier\Exceptions\ResponseException $exception) {
    dump($exception->context()) // Dumps all the information passed into Guzzle, including headers and configuration
    dump($exception->getUrl()) // Dumps the url the request was sent to
    dump($exception->getPayload()) // Dumps the payload that was sent to Vandar APIs
    dump($exception->getResponse()) // Dumps the response object returned by Guzzle
    dump($exception->getResponse()->json()) // Returns an associative array of json response.
    dump($exception->errors()) // Useful especially in InvalidPayloadException, returns the "errors" key in the json response
}
```
You may also attempt to differentiate between the errors based on the exception that was raised:

* **ExternalAPIException** is raised when any 5xx error occurs
* **AuthenticationException** is raised when there's an HTTP 401 error, this may only happen if you forgot to set your env or if your information is invalid, if this occurs in any other case, do let us know!
* **DeprecatedAPIException** is raised when there's either an HTTP 404, 405 or 410 error, this probably means that the version of Vandar Cashier (and the APIs behind it) used is no longer supported by Vandar, run `composer update`!
* **InvalidPayloadException** is raised when the attempted action was in any way invalid on Vandar's side raising an HTTP 422. (e.g. you're authorized to withdraw 1000 Toman and you attempt to withdraw more), We aim to break this down into more specific exceptions in the future.
* **TooManyRequestsException** is raised if you attempt to send a very large sum of requests to Vandar in a short period of time, causing an HTTP 429 error.
* **UnexpectedAPIErrorException** is raised when any other 4xx series error not in the scope of Vandar Cashier is returned. if this happens, consider updating Cashier or filing a bug with the full context of the exception you got.

# License
All material in this project (unless otherwise noted) are available under the MIT License. See LICENSE for more
information.
