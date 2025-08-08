import { defineStore } from 'pinia';
import api from '@/utils/api';
import type { RoundFeatureMatrixResponse } from '@/types/prediction';
import { websocketManager } from '@/utils/websocketManager';

interface FeatureStoreState {
  matrix: RoundFeatureMatrixResponse | null;
  loading: boolean;
  error: string | null;
  updatedAt: number | null;
}

export const useFeatureStore = defineStore('featureStore', {
  state: (): FeatureStoreState => ({
    matrix: null,
    loading: false,
    error: null,
    updatedAt: null
  }),
  actions: {
    async fetchRoundFeatures(roundId?: string | number): Promise<void> {
      try {
        this.loading = true;
        this.error = null;
        const url = roundId ? `/v3/features/round/${roundId}` : '/v3/features/round';
        const { data } = await api.get(url);
        if (data?.success && data?.data) {
          this.matrix = data.data as RoundFeatureMatrixResponse;
          this.updatedAt = Date.now();
        } else {
          this.matrix = {
            round_id: String(roundId ?? ''),
            tokens: [],
            features: [],
            matrix: {},
            computed_at: new Date().toISOString()
          };
        }
      } catch (e: any) {
        this.error = e?.message ?? '获取特征矩阵失败';
      } finally {
        this.loading = false;
      }
    },
    subscribeFeatureMatrixPush(): void {
      if (!websocketManager.isInitialized) websocketManager.initialize();
      websocketManager.listenToFeatureMatrix((event: any) => {
        const payload = event?.data;
        if (!payload) return;
        this.matrix = payload as RoundFeatureMatrixResponse;
        this.updatedAt = Date.now();
      });
    },
    clear(): void {
      this.matrix = null;
      this.error = null;
      this.updatedAt = null;
      this.loading = false;
    }
  }
});
