import { defineStore } from 'pinia';
import api from '@/utils/api';
import type { RoundFeatureMatrixResponse } from '@/types/prediction';
import { websocketManager } from '@/utils/websocketManager';

interface FeatureStoreState {
  matrix: RoundFeatureMatrixResponse | null;
  loading: boolean;
  error: string | null;
  updatedAt: number | null;
  lastRoundId?: string | null;
  pushReceivedAt?: number | null;
  fallbackTimerId?: number | null;
}

export const useFeatureStore = defineStore('featureStore', {
  state: (): FeatureStoreState => ({
    matrix: null,
    loading: false,
    error: null,
    updatedAt: null,
    lastRoundId: null,
    pushReceivedAt: null,
    fallbackTimerId: null
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
          this.lastRoundId = String((this.matrix as any)?.round_id ?? '');
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
        this.lastRoundId = String((payload as any)?.round_id ?? event?.round_id ?? '');
        this.pushReceivedAt = Date.now();
        // 收到推送后，取消兜底请求计时器
        if (this.fallbackTimerId) {
          clearTimeout(this.fallbackTimerId);
          this.fallbackTimerId = null;
        }
      });
    },
    maybeFetchAfterTimeout(roundId?: string, timeoutMs = 3000): void {
      const start = Date.now();
      const matchedRound = roundId ? this.lastRoundId === roundId : true;
      const recentPush = this.pushReceivedAt && Date.now() - this.pushReceivedAt < timeoutMs;
      if (matchedRound && recentPush) return; // 已有有效推送，跳过请求

      // 统一去抖：始终只保留一个兜底计时器，避免并发两次请求
      if (this.fallbackTimerId) {
        clearTimeout(this.fallbackTimerId);
      }
      this.fallbackTimerId = window.setTimeout(() => {
        this.fallbackTimerId = null;
        const gotPush = this.pushReceivedAt && this.pushReceivedAt >= start;
        const sameRound = roundId ? this.lastRoundId === roundId : !!this.matrix;
        if (!gotPush || !sameRound) {
          void this.fetchRoundFeatures(roundId);
        }
      }, timeoutMs);
    },
    clear(): void {
      this.matrix = null;
      this.error = null;
      this.updatedAt = null;
      this.loading = false;
      this.lastRoundId = null;
      this.pushReceivedAt = null;
      if (this.fallbackTimerId) {
        clearTimeout(this.fallbackTimerId);
        this.fallbackTimerId = null;
      }
    }
  }
});
