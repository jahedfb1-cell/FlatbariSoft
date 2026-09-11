import client from './client';

export interface GasConsumer {
  id: number;
  flat_id: number | null;
  flat_no: string | null;
  name: string;
  meter_no: string | null;
  is_internal: boolean;
  active: boolean;
  last_reading: string | null;
}

export function listGasConsumers() {
  return client.get<GasConsumer[]>('/gas/consumers').then((r) => r.data);
}

export function recordGasReading(data: {
  gas_consumer_id: number;
  billing_period_id: number;
  current_reading: number | string;
  reading_date: string;
}) {
  return client.post('/gas/readings', data).then((r) => r.data);
}

export function applyGasToBills(billingPeriodId: number) {
  return client.post(`/gas/periods/${billingPeriodId}/apply`).then((r) => r.data);
}
