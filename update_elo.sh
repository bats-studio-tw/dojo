#!/bin/bash

# Elo评分批量更新脚本
# 使用方法: ./update_elo.sh [选项]

echo "🎯 Elo评分批量更新工具"
echo "========================"

# 默认参数
LIMIT=5000
MODE="first-vs-all"
RESET=false
DRY_RUN=true

# 解析命令行参数
while [[ $# -gt 0 ]]; do
    case $1 in
        --limit)
            LIMIT="$2"
            shift 2
            ;;
        --mode)
            MODE="$2"
            shift 2
            ;;
        --reset)
            RESET=true
            shift
            ;;
        --execute)
            DRY_RUN=false
            shift
            ;;
        --help)
            echo "使用方法: $0 [选项]"
            echo ""
            echo "选项:"
            echo "  --limit N     处理的轮次数量 (默认: 5000)"
            echo "  --mode MODE   胜负关系模式 (first-vs-all, top3-vs-all, all-vs-all)"
            echo "  --reset       重置所有代币评分到1500"
            echo "  --execute     实际执行更新 (默认是试运行)"
            echo "  --help        显示此帮助信息"
            echo ""
            echo "示例:"
            echo "  $0 --limit 1000 --mode first-vs-all"
            echo "  $0 --reset --execute --limit 5000"
            exit 0
            ;;
        *)
            echo "未知选项: $1"
            echo "使用 --help 查看帮助信息"
            exit 1
            ;;
    esac
done

# 构建命令
CMD="php artisan elo:batch-update --limit=$LIMIT --mode=$MODE"

if [ "$RESET" = true ]; then
    CMD="$CMD --reset"
fi

if [ "$DRY_RUN" = true ]; then
    CMD="$CMD --dry-run"
    echo "🔍 试运行模式 (使用 --execute 进行实际更新)"
else
    echo "⚠️  实际执行模式 - 将更新数据库"
fi

echo "📊 处理轮次: $LIMIT"
echo "🎮 胜负模式: $MODE"
echo "🔄 重置模式: $RESET"
echo ""

# 确认执行
if [ "$DRY_RUN" = false ]; then
    read -p "确认要执行实际更新吗? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ 已取消"
        exit 1
    fi
fi

echo "🚀 开始执行..."
echo "命令: $CMD"
echo ""

# 执行命令
eval $CMD

echo ""
echo "✅ 完成!"
