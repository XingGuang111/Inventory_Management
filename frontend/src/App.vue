<template>
  <el-container class="app">
    <el-aside width="220px" class="aside">
      <div class="logo">商品库存管理系统</div>
      <el-menu :default-active="route.path" router background-color="#001529" text-color="#fff" active-text-color="#409EFF">
        <el-menu-item index="/goods">商品清单</el-menu-item>
        <el-menu-item index="/in">入库管理</el-menu-item>
        <el-menu-item index="/out">出库管理</el-menu-item>
        <el-menu-item index="/stock">库存查询</el-menu-item>
        <el-menu-item index="/check">库存盘点</el-menu-item>
      </el-menu>
    </el-aside>
    <el-container>
      <el-header class="header">{{ route.meta.title || '库存管理' }}</el-header>
      <el-main><router-view /></el-main>
    </el-container>
  </el-container>
</template>

<script setup>
import { useRoute } from 'vue-router'
const route = useRoute()
</script>

<style>
html, body, #app { height: 100%; margin: 0; }
.app { height: 100vh; }
.aside { background: #001529; }
.logo { color: #fff; text-align: center; padding: 18px 0; font-size: 16px; font-weight: bold; border-bottom: 1px solid #1a3a5c; }
.header { background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); line-height: 60px; font-size: 18px; font-weight: bold; }
.el-menu { border: none !important; }

/* ===== 禁止用户调整表格列宽 =====
   Element Plus 的列拖拽调整通过 th 边缘 mousedown 触发；
   将 th 的 pointer-events 关闭、仅内层 .cell 保留交互，即可屏蔽拖拽 */
.el-table th.el-table__cell { pointer-events: none !important; cursor: default !important; }
.el-table th.el-table__cell .cell,
.el-table th.el-table__cell .caret-wrapper { pointer-events: auto; }
.el-table__column-resize-proxy { display: none !important; }

/* ===== 禁止 textarea 拖拽缩放 ===== */
.el-textarea__inner, textarea { resize: none !important; }

/* ===== 禁止 dialog 手动缩放（默认就不可缩放，这里再兜底一次） ===== */
.el-dialog { resize: none !important; }

/* ===== 打印区域（在页面上不可见，但保留 DOM 供 PrintJS 克隆） ===== */
.print-hidden { position: fixed; left: -10000px; top: 0; width: 800px; }
</style>
