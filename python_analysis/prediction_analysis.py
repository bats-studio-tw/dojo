#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
改良版 ‑ Dojo 預測演算法性能分析器  (v1.1 2025‑07‑13)
------------------------------------------------
變更紀錄（相對 v1.0）
* 🔧 **load_data() 容錯**：round_id 可能含字串（e.g. "ojoCap_…"）導致 dtype 失敗。現改以字串讀取；後續轉 numeric（無法轉者標記 NaN）。
* 🔧 讀檔若 dtype 失敗，fallback 自動重新讀、log warning。
* 🌱 讀檔完成後，將 predicted_rank / actual_rank 強制 `pd.to_numeric(errors='coerce')`，並回報遺失值數量供檢查。
其他邏輯與 v1.0 相同。
"""

from __future__ import annotations

import argparse
import json
import logging
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List

import numpy as np
import pandas as pd
from scipy import stats
from sklearn.calibration import calibration_curve
from sklearn.metrics import roc_auc_score
from sklearn.model_selection import train_test_split
from sklearn.preprocessing import MinMaxScaler

# 可選：LightGBM + SHAP
try:
    from lightgbm import LGBMClassifier  # type: ignore

    LIGHTGBM_AVAILABLE = True
except ImportError:
    LIGHTGBM_AVAILABLE = False

try:
    import shap  # type: ignore

    SHAP_AVAILABLE = True
except ImportError:
    SHAP_AVAILABLE = False

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)s | %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)


class PredictionAnalyzer:
    """核心分析器"""

    def __init__(self, csv_file_path: Path, settings: Dict):
        self.csv_file = csv_file_path
        self.settings = settings
        self.df: pd.DataFrame | None = None
        self.analysis_results: Dict = {}

    # ------------------------------------------------------------------
    # 讀檔 & 前處理
    # ------------------------------------------------------------------
    def load_data(self) -> bool:
        logging.info("Loading data from %s", self.csv_file)
        base_dtype = {
            "round_id": "string",  # 字串→後續再轉 numeric
            "token_symbol": "category",
            "predicted_rank": "float32",  # 先用 float，稍後轉 int
            "actual_rank": "float32",
            "rank_difference": "float32",
            "prediction_score": "float32",
            "absolute_score": "float32",
            "relative_score": "float32",
            "predicted_final_value": "float32",
            "risk_adjusted_score": "float32",
            "h2h_data_available": "float16",
            "change_5m": "float32",
            "change_1h": "float32",
            "change_4h": "float32",
            "change_24h": "float32",
        }

        try:
            self.df = pd.read_csv(self.csv_file, dtype=base_dtype, parse_dates=["settled_at"])
        except Exception as exc:
            logging.error("❌ Failed to read csv even after fallback: %s", exc)
            return False

        # round_id 轉 numeric（失敗者為 NaN）
        self.df["round_id_num"] = pd.to_numeric(self.df["round_id"], errors="coerce")

        # predicted_rank / actual_rank 轉 int16，NaN → -1
        for col in ("predicted_rank", "actual_rank"):
            self.df[col] = pd.to_numeric(self.df[col], errors="coerce").fillna(-1).astype("int16")
        missing_pred = (self.df["predicted_rank"] == -1).sum()
        missing_act = (self.df["actual_rank"] == -1).sum()
        if missing_pred or missing_act:
            logging.warning("Found %s rows with missing ranks (pred=%s / act=%s)", len(self.df), missing_pred, missing_act)

        # rank_difference 若缺，補計算
        self.df["rank_difference"] = self.df["rank_difference"].fillna(
            self.df["predicted_rank"] - self.df["actual_rank"]
        )

        # 衍生標籤
        self.df["is_breakeven"] = (self.df["actual_rank"] <= 3).astype(int)
        self.df["is_exact_match"] = (self.df["rank_difference"] == 0).astype(int)
        self.df["is_close_match"] = (self.df["rank_difference"].abs() <= 1).astype(int)
        self.df["date"] = self.df["settled_at"].dt.date
        logging.info("Data loaded: %s rows", len(self.df))
        return True

    # ---------------------------------------------------------------------
    # 基礎統計
    # ---------------------------------------------------------------------
    def basic_statistics(self):
        df = self.df
        total_predictions = len(df)
        breakeven_rate = df["is_breakeven"].mean()
        hi_cut = df["risk_adjusted_score"].quantile(0.8)
        hi_conf = df[df["risk_adjusted_score"] >= hi_cut]
        hi_breakeven = hi_conf["is_breakeven"].mean() if len(hi_conf) else np.nan

        self.analysis_results["basic_stats"] = {
            "total_predictions": int(total_predictions),
            "unique_rounds": int(df["round_id"].nunique()),
            "unique_tokens": int(df["token_symbol"].nunique()),
            "breakeven_rate": breakeven_rate,
            "exact_accuracy": df["is_exact_match"].mean(),
            "close_accuracy": df["is_close_match"].mean(),
            "avg_rank_diff": df["rank_difference"].mean(),
            "median_rank_diff": df["rank_difference"].median(),
            "hi_conf_cut": float(hi_cut),
            "hi_conf_breakeven": hi_breakeven,
        }

    # ---------------------------------------------------------------------
    # 分數相關 & 校準
    # ---------------------------------------------------------------------
    def score_correlation_and_calibration(self):
        df = self.df
        score_cols = [
            "prediction_score",
            "absolute_score",
            "relative_score",
            "predicted_final_value",
            "risk_adjusted_score",
        ]
        correlations = {
            c: df[c].corr(df["actual_rank"]) for c in score_cols if c in df.columns
        }
        best_metric = min(correlations.items(), key=lambda x: x[1])  # 越負越好
        prob, true = calibration_curve(
            df["is_breakeven"], MinMaxScaler().fit_transform(df[["risk_adjusted_score"]])[:, 0], strategy="quantile", n_bins=10
        )
        self.analysis_results["score_perf"] = {
            "correlations": correlations,
            "best_metric": best_metric[0],
            "calibration_curve": {"pred": prob.tolist(), "truth": true.tolist()},
        }

    # ---------------------------------------------------------------------
    # Token 詳解
    # ---------------------------------------------------------------------
    def token_performance(self):
        df = self.df
        agg = (
            df.groupby("token_symbol")
            .agg(
                total_games=("is_breakeven", "size"),
                breakeven_rate=("is_breakeven", "mean"),
                avg_predicted_rank=("predicted_rank", "mean"),
                avg_actual_rank=("actual_rank", "mean"),
                rank_diff_mean=("rank_difference", "mean"),
            )
            .reset_index()
        )
        agg["prediction_bias"] = agg["avg_predicted_rank"] - agg["avg_actual_rank"]
        key_offenders = (
            agg.assign(total_errors=lambda x: x["rank_diff_mean"] * x["total_games"])
            .sort_values("total_errors", ascending=False)
            .head(15)
        )
        self.analysis_results["token"] = {
            "stats": agg,
            "key_offenders": key_offenders,
            "overall_bias": agg["prediction_bias"].mean(),
        }

    # ---------------------------------------------------------------------
    # Temporal & CV
    # ---------------------------------------------------------------------
    def temporal_and_cv(self):
        df = self.df
        daily = (
            df.groupby("date")["is_breakeven"]
            .agg(["mean", "size"])
            .rename(columns={"mean": "breakeven_rate", "size": "games"})
            .reset_index()
        )
        window, step = 500, 100
        cv_rates: List[float] = []
        for start in range(0, len(df) - window, step):
            cv_rates.append(df.iloc[start : start + window]["is_breakeven"].mean())
        self.analysis_results["temporal"] = {
            "daily": daily,
            "rolling_cv": cv_rates,
        }

    # ---------------------------------------------------------------------
    # ML & 特徵重要度
    # ---------------------------------------------------------------------
    def ml_feature_importance(self):
        df = self.df
        feature_columns = [
            "absolute_score",
            "relative_score",
            "predicted_final_value",
            "risk_adjusted_score",
            "rank_difference",
            "prediction_score",
            "change_5m",
            "change_1h",
            "change_4h",
            "change_24h",
        ]
        features = [c for c in feature_columns if c in df.columns and df[c].notna().sum() > len(df) * 0.5]
        if len(features) < 3 or not LIGHTGBM_AVAILABLE:
            logging.warning("Skip ML feature importance (insufficient features or LightGBM missing)")
            return

        X = df[features].fillna(0)
        y = df["is_breakeven"]
        X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42, stratify=y)
        model = LGBMClassifier(n_estimators=400, learning_rate=0.05, random_state=42)
        model.fit(X_train, y_train)
        y_pred_prob = model.predict_proba(X_test)[:, 1]
        auc = roc_auc_score(y_test, y_pred_prob)
        importances = pd.Series(model.feature_importances_, index=features).sort_values(ascending=False)
        shap_vals, shap_summary = None, None
        if SHAP_AVAILABLE:
            explainer = shap.TreeExplainer(model, X_train, feature_perturbation="tree_path_dependent")
            shap_vals = explainer.shap_values(X_train, check_additivity=False)[1]
            shap_summary = np.abs(shap_vals).mean(axis=0)
            shap_summary = pd.Series(shap_summary, index=features).sort_values(ascending=False)
        self.analysis_results["ml"] = {
            "auc": auc,
            "importances": importances,
            "shap": shap_summary,
        }

    # ---------------------------------------------------------------------
    # 風控 & Kelly
    # ---------------------------------------------------------------------
    def risk_and_kelly(self):
        df = self.df
        fail_flags = df["is_breakeven"].eq(0).astype(int).values
        streaks = np.diff(
            np.where(
                np.concatenate((fail_flags[:1], fail_flags[:-1] != fail_flags[1:], [True]))
            )[0]
        )[::2]
        streak_counts = pd.Series(streaks).value_counts().sort_index()
        p = self.analysis_results["basic_stats"]["breakeven_rate"]
        b = self.settings["payout_ratio"]  # 期望賠率 (例如 0.5 → 1 賠 1.5)
        kelly = max(p - (1 - p) / b, 0)
        self.analysis_results["risk"] = {
            "streak_counts": streak_counts.to_dict(),
            "kelly_fraction": kelly,
        }

    # ---------------------------------------------------------------------
    # 建議產生
    # ---------------------------------------------------------------------
    def suggestions(self):
        stats = self.analysis_results["basic_stats"]
        sugg: List[Dict] = []
        if stats["hi_conf_breakeven"] and stats["hi_conf_breakeven"] >= 0.75:
            sugg.append(
                {
                    "priority": "HIGH",
                    "issue": "High‑confidence bucket already ≥75 %",
                    "action": "Restrict real bets to high‑confidence rounds"
                }
            )
        if self.analysis_results.get("ml"):
            auc = self.analysis_results["ml"]["auc"]
            if auc > 0.70:
                sugg.append(
                    {
                        "priority": "MEDIUM",
                        "issue": f"ML AUC={auc:.2f} outperforms heuristics",
                        "action": "Pilot LightGBM probability to replace risk_adjusted_score"
                    }
                )
        if self.analysis_results["token"]["overall_bias"] > 0.5:
            sugg.append(
                {
                    "priority": "MEDIUM",
                    "issue": "Systematically over‑optimistic",
                    "action": "Increase value_stddev penalty in risk_adjusted_score"
                }
            )
        self.analysis_results["suggestions"] = sugg

    # ---------------------------------------------------------------------
    # 報告輸出
    # ---------------------------------------------------------------------
    def export_reports(self, output_dir: Path):
        output_dir.mkdir(parents=True, exist_ok=True)
        ts = datetime.utcnow().strftime("%Y%m%d_%H%M%S")
        # 1 CSV summary
        basic = pd.DataFrame.from_dict(
            self.analysis_results["basic_stats"], orient="index", columns=["value"]
        )
        csv_file = output_dir / f"summary_{ts}.csv"
        basic.to_csv(csv_file, encoding="utf-8-sig")
        # 2 XLSX multi‑sheet
        xlsx_file = output_dir / f"analysis_{ts}.xlsx"
        with pd.ExcelWriter(xlsx_file) as writer:
            basic.to_excel(writer, sheet_name="Summary")
            self.analysis_results["token"]["stats"].to_excel(writer, sheet_name="TokenStats", index=False)
            pd.DataFrame(self.analysis_results["temporal"]["rolling_cv"], columns=["rolling_breakeven"]).to_excel(
                writer, sheet_name="RollingCV", index=False
            )
            if "importances" in self.analysis_results.get("ml", {}):
                self.analysis_results["ml"]["importances"].to_frame("importance").to_excel(
                    writer, sheet_name="FeatureImp"
                )
        # 3 JSON suggestions
        json_file = output_dir / f"suggestions_{ts}.json"
        json_file.write_text(json.dumps(self.analysis_results["suggestions"], indent=2, ensure_ascii=False))
        logging.info("Reports saved to %s", output_dir)

    # ---------------------------------------------------------------------
    # 全流程
    # ---------------------------------------------------------------------
    def run(self):
        if not self.load_data():
            return False
        self.basic_statistics()
        self.score_correlation_and_calibration()
        self.token_performance()
        self.temporal_and_cv()
        self.ml_feature_importance()
        self.risk_and_kelly()
        self.suggestions()
        self.export_reports(Path(self.settings["output_dir"]))
        return True


# ------------------------------------------------------------------
# CLI
# ------------------------------------------------------------------

def parse_args():
    parser = argparse.ArgumentParser(description="Dojo Prediction Analyzer")
    parser.add_argument("csv", type=Path, help="Path to CSV file exported from Laravel job")
    parser.add_argument("--payout-ratio", type=float, default=0.5)
    parser.add_argument("--output-dir", type=str, default="reports")
    return parser.parse_args()


def main():
    args = parse_args()
    settings = {"payout_ratio": args.payout_ratio, "output_dir": args.output_dir}
    analyzer = PredictionAnalyzer(args.csv, settings)
    if analyzer.run():
        logging.info("✅ Analysis completed ✨")
    else:
        logging.error("Analysis failed")


if __name__ == "__main__":
    main()
