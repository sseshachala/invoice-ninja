<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveUsersForeignKeys extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::table('account_gateways', function($table)
		{
			$table->dropForeign('account_gateways_user_id_foreign');
		});
		Schema::table('account_tokens', function($table)
		{
			$table->dropForeign('account_tokens_user_id_foreign');
		});
		Schema::table('clients', function($table)
		{
			$table->dropForeign('clients_user_id_foreign');
		});
		Schema::table('contacts', function($table)
		{
			$table->dropForeign('contacts_user_id_foreign');
		});
		Schema::table('credits', function($table)
		{
			$table->dropForeign('credits_user_id_foreign');
		});
		Schema::table('invitations', function($table)
		{
			$table->dropForeign('invitations_user_id_foreign');
		});
		Schema::table('invoices', function($table)
		{
			$table->dropForeign('invoices_user_id_foreign');
		});
		Schema::table('invoice_items', function($table)
		{
			$table->dropForeign('invoice_items_user_id_foreign');
		});
		Schema::table('payments', function($table)
		{
			$table->dropForeign('payments_user_id_foreign');
		});
		Schema::table('products', function($table)
		{
			$table->dropForeign('products_user_id_foreign');
		});
		Schema::table('tasks', function($table)
		{
			$table->dropForeign('tasks_user_id_foreign');
		});
		Schema::table('tax_rates', function($table)
		{
			$table->dropForeign('tax_rates_user_id_foreign');
		});
		Schema::table('user_accounts', function($table)
		{
			$table->dropForeign('user_accounts_user_id1_foreign');
			$table->dropForeign('user_accounts_user_id2_foreign');
			$table->dropForeign('user_accounts_user_id3_foreign');
			$table->dropForeign('user_accounts_user_id4_foreign');
			$table->dropForeign('user_accounts_user_id5_foreign');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		//
	}

}
