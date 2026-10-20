@extends('backend.layouts.app')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">
<!-- import Vue before Element -->
<script src="https://unpkg.com/vue@2/dist/vue.js"></script>
<!-- import JavaScript -->
<script src="https://unpkg.com/element-ui/lib/index.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>
<br>

<div class="card" id="app">
    <form class="" id="sort_products" action="" method="GET">
        <div class="card-header row gutters-5">
            <div class="col">
                <el-button type="primary" size="small" @click="getData()">加载列表</el-button>
            </div>

            <div class="dropdown mb-2 mb-md-0">
                <el-button type="primary" size="small" @click="storage()">{{translate('Product Storage')}}</el-button>
            </div>
            <div class="col-md-2 ml-auto">
                <el-select v-model="category_id" placeholder="请选择">
                    <el-option
                        v-for="item in options"
                        :key="item.category_id"
                        :label="item.name"
                        :value="item.id">
                    </el-option>
                </el-select>
            </div>
        </div>

        <div class="card-body">
            <el-table
                ref="multipleTable"
                @selection-change="handleSelectionChange"
                :data="tableData"
                style="width: 100%">
                <el-table-column
                    type="selection"
                    width="55">
                </el-table-column>
                <el-table-column
                    prop="id"
                    label="ID">
                </el-table-column>
                <el-table-column
                    prop="name"
                    label="姓名"
                    width="450">
                    <template slot-scope="props">
                        <div class="row gutters-5 w-200px w-md-300px mw-100" v-if="props.row.name">
                            <div class="col-auto">
                                <img :src="props.row.pic_url" alt="Image" class="size-50px img-fit">
                            </div>
                            <div class="col" v-html="props.row.name"></div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="source"
                    label="来源">
                </el-table-column>
                <el-table-column
                    prop="price"
                    label="信息">
                    <template slot-scope="props" v-if="props.row.price">
                        <strong>{{translate('Num of Sale')}}:</strong> <span>0</span>
                        <br></br>
                        <strong>{{translate('Base Price')}}:</strong> <span v-html="props.row.price"></span>
                        <br></br>
                        <strong>{{translate('Rating')}}:</strong> <span v-html="props.row.rating"></span>
                        <br></br>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="stock"
                    label="总库存">
                </el-table-column>
                <el-table-column
                    prop="comment"
                    label="评价">
                    <template #default="{ row }">
                        <el-switch
                            v-if="row.comment !==''"
                            v-model="row.comment"
                            active-color="#1890FF"
                            inactive-color="#A9A8A8"
                            @change="turn(row)"
                        >
                        </el-switch>
                    </template>
                </el-table-column>
            </el-table>
            <div style="margin-top: 10px"></div>
            <div class="block">
                <el-pagination
                    @current-change="handleCurrentChange"
                    :current-page.sync="page"
                    :page-size="limit"
                    layout="total, prev, pager, next"
                    :total="count">
                </el-pagination>
            </div>
        </div>
    </form>
</div>

@endsection

@section('modal')
    <div id="movie-out-modal" class="modal fade">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title h6">{{translate('Move Out Confirmation')}}</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mt-1">{{translate('Are you sure to move this out?')}}</p>
                    <button type="button" class="btn btn-link mt-2" data-dismiss="modal">{{translate('Cancel')}}</button>
                    <a href="" id="comfirm-link" class="btn btn-primary mt-2">{{translate('Move Out')}}</a>
                </div>
            </div>
        </div>
    </div>
    @include('modals.delete_modal')
@endsection


@section('script')
    <script type="text/javascript">
        new Vue({
            el: '#app',
            data: {
                tableData: [],
                multipleSelection: [],
                count: 0,
                limit: 5,
                page: 1,
                storaging: false,
                options: [],
                category_id: '',
                type: 'lazada',//默认lazada alibaba
            },
            mounted(){
                this.getIds()
            },
            methods: {
                handleCurrentChange(val) {
                    this.page = val
                    this.getData()
                    console.log(`当前页: ${val}`);
                },
                turn(row) {
                    let list = this.tableData;
                    for(let i = 0;i < list.length;i++){
                        if(list[i].id == row.id){
                            list[i].rating = row.rating
                        }
                    }
                    this.tableData = list;
                },
                getData(){
                    const that = this
                    let url = 'data?page='+ that.page+ '&limit='+that.limit+ '&type='+that.type
                    axios
                        .get(url)
                        .then(function (response) {
                            that.tableData = response.data.products;
                            that.count = response.data.count;
                            that.limit = response.data.limit;
                            that.page = response.data.page;
                            that.pages = response.data.pages;
                            that.type = response.data.type
                            console.log(response);
                        })
                        .catch(function (error) { // 请求失败处理
                            console.log(error);
                        });
                },
                getIds(){
                    const that = this
                    axios
                        .get('ids'+ '?type='+that.type)
                        .then( function(res){
                            that.tableData = res.data.params
                            that.options = res.data.categorys
                            that.type = res.data.type
                        })
                        .catch(function (error) { // 请求失败处理
                            console.log(error);
                        });
                },
                toggleSelection(rows) {
                    if (rows) {
                        rows.forEach(row => {
                            this.$refs.multipleTable.toggleRowSelection(row);
                        });
                    } else {
                        this.$refs.multipleTable.clearSelection();
                    }
                },
                handleSelectionChange(val) {
                    this.multipleSelection = val;
                    console.log(val);
                },
                storage(){
                    if(this.category_id==''){
                        this.$message({
                            message: '请选择分类',
                            type: 'error'
                        });
                        return false;
                    }
                    if(this.storaging===true){
                        return true
                    }
                    this.storaging = true
                    if(this.multipleSelection.length ===0){
                        this.$message({
                            message: '请选择入库产品',
                            type: 'error'
                        });
                        return false;
                    }
                    const that = this
                    axios
                        .post('collect/bulk-product-collect-add',{
                            ids: this.multipleSelection,
                            category_id: this.category_id,
                            type: this.type
                        })
                        .then( function(res){
                            that.$message({
                                message: '入库成功',
                                type: 'success'
                            });
                            that.page =  that.page+ 1
                            that.getData()
                            that.storaging = false
                        })
                        .catch(function (error) { // 请求失败处理
                            console.log(error);
                            that.storaging = false
                        });
                }
            }
        })

    </script>
@endsection
