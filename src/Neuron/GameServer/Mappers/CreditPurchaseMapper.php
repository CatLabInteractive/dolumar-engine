<?php

/**
 * Persistence for n_credit_purchases. Status changes are compare-and-set:
 * transition() returns true only for the single caller whose UPDATE changed
 * the row, which is what makes grants and refunds happen exactly once.
 */
class Neuron_GameServer_Mappers_CreditPurchaseMapper
{
	public static function getInstance (): self
	{
		static $in;
		if (!isset ($in))
		{
			$in = new self ();
		}
		return $in;
	}

	public function insert (int $plid, int $credits, string $description, array $actionData, string $createdAt, string $expiresAt): int
	{
		$db = Neuron_DB_Database::getInstance ();
		$id = $db->query
		(
			"INSERT INTO n_credit_purchases (plid, credits, description, action_data, status, created_at, expires_at, updated_at) VALUES ("
			. (int) $plid . ", " . (int) $credits . ", "
			. "'" . $db->escape ($description) . "', "
			. "'" . $db->escape (json_encode ($actionData)) . "', 'pending', "
			. "'" . $db->escape ($createdAt) . "', '" . $db->escape ($expiresAt) . "', '" . $db->escape ($createdAt) . "')"
		);
		return (int) $id;
	}

	public function setRequestId (int $id, int $requestId): void
	{
		Neuron_DB_Database::getInstance ()->query
		(
			"UPDATE n_credit_purchases SET credit_request_id = " . (int) $requestId . " WHERE id = " . (int) $id
		);
	}

	/**
	 * Compare-and-set the status. True only when exactly this call changed the row.
	 */
	public function transition (int $id, string $from, string $to, string $now): bool
	{
		$db = Neuron_DB_Database::getInstance ();
		$db->query
		(
			"UPDATE n_credit_purchases SET status = '" . $db->escape ($to) . "', updated_at = '" . $db->escape ($now) . "' "
			. "WHERE id = " . (int) $id . " AND status = '" . $db->escape ($from) . "'"
		);
		return $db->getAffectedRows () === 1;
	}

	public function get (int $id): ?array
	{
		return $this->fetchOne ("WHERE id = " . (int) $id);
	}

	public function getByRequestId (int $requestId): ?array
	{
		return $this->fetchOne ("WHERE credit_request_id = " . (int) $requestId);
	}

	/**
	 * Purchases that can still be settled: pending, plus recently expired
	 * ones (a late confirmation on Accounts must be refunded).
	 */
	public function getSettleable (int $limit, string $expiredSince): array
	{
		$db = Neuron_DB_Database::getInstance ();
		return $this->fetchAll
		(
			"WHERE credit_request_id IS NOT NULL AND (status = 'pending' OR (status = 'expired' AND updated_at >= '"
			. $db->escape ($expiredSince) . "')) ORDER BY id ASC LIMIT " . max (1, $limit)
		);
	}

	public function getForPlayer (int $plid): array
	{
		return $this->fetchAll ("WHERE plid = " . (int) $plid . " ORDER BY id DESC LIMIT 100");
	}

	private function fetchOne (string $where): ?array
	{
		$rows = $this->fetchAll ($where . " LIMIT 1");
		return $rows[0] ?? null;
	}

	private function fetchAll (string $where): array
	{
		$result = Neuron_DB_Database::getInstance ()->query ("SELECT * FROM n_credit_purchases " . $where);
		$out = [];
		foreach ($result as $row)
		{
			$data = json_decode ((string) $row['action_data'], true);
			$out[] = [
				'id' => (int) $row['id'],
				'plid' => (int) $row['plid'],
				'credits' => (int) $row['credits'],
				'description' => (string) $row['description'],
				'action_data' => is_array ($data) ? $data : [],
				'status' => (string) $row['status'],
				'credit_request_id' => $row['credit_request_id'] === null ? null : (int) $row['credit_request_id'],
				'created_at' => (string) $row['created_at'],
				'expires_at' => (string) $row['expires_at'],
				'updated_at' => (string) $row['updated_at'],
			];
		}
		return $out;
	}
}
