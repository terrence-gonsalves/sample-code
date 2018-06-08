var acc = document.getElementsByClassName("accordion");

for (let i = 0; i < acc.length; i++) {
    acc[i].onclick = function () {
        this.classList.toggle("active");
        var panel = this.nextElementSibling;
        if (panel.style.maxHeight) {
            panel.style.maxHeight = null;
        } else {
            panel.style.maxHeight = panel.scrollHeight + "px";
        }
    }
}

function sharingToolToggle() {

    // grab the entire ul element to loop through and add the class
    var sharing_tool_children,
    sharing_tool = document.querySelector('.social-sharing-sidebar');

    // check to make sure that the element exist
    if (sharing_tool) {
        sharing_tool_children = sharing_tool.getElementsByTagName('li');

        // loop through each child element and add the open class
        for (var increment = 0; increment < sharing_tool_children.length; increment++) {

            // slide the shring icon down
            if (!sharing_tool_children[increment].classList.contains('sharing-open')) {
                sharing_tool_children[increment].classList.add('sharing-open', 'fadeIn', 'animated');
            } else {
                sharing_tool_children[increment].classList.remove('sharing-open', 'fadeIn', 'animated');
                sharing_tool_children[increment].classList.add('fadeOut', 'animated');
                sharing_tool_children[increment].classList.remove('fadeOut', 'animated');
            }
        }
    }
}

// Build Elastisearch Query
function createESQuery( post_type, search_terms, filtered_array, policy_status, agree_score ) {
  if ( Object.keys( filtered_array ).length !== 0 ) {
    var esquery = {
      from: 0,
      size: 10000
    };

    // sorting is different with search terms
    if ( search_terms !== '' ) {
      esquery.query = {
        bool: {
            should: [
                {
                    multi_match: {
                        query: search_terms,
                        type: 'phrase',
                        fields:  $fields,
                        boost: 4
                    }
                },
                {
                    multi_match: {
                        query: search_terms,
                        fields: $fields,
                        boost: 2,
                        fuzziness: 0,
                        operator: "and"
                    }
                },
                {
                    multi_match: {
                        fields: $fields,
                        query: search_terms,
                        fuzziness: 1
                    }
                }
            ]
        }
      }
    } else {
      esquery.query = {
          match_all: {
              boost: 1
          }
      }
    }

    esquery.sort = [
        {
            post_date: {
                order: 'desc'
            }
        }
    ];

   for ( var dbt in filtered_array ) {
      switch ( dbt ) {
        case 'corporate-documents':
          $dbv = dbt
          dbt = 'corporate_documents_categories';
          break;
        case 'news-events':
          $dbv = dbt
          dbt = 'news_events_categories';
          break;
        case 'procurement-details':
          $dbv = dbt
          dbt = 'post_type';
          break;
        case 'job-posting':
          $dbv = dbt
          dbt = 'job_posting_categories';
          break;
        case 'db-prevention-policy':
        case 'db-sage':
          $dbv = '';
          break;
      }

      if ( $dbv !== '' && $dbv !== undefined ) {
        if ( dbt !== 'post_type' ) {
        for ( let i = 0; i < filtered_array[ $dbv ].length; i++ ) {
            $terms.push( {
                terms: { [ 'terms.' + dbt +  '.slug' ]: [ filtered_array[ $dbv ][ i ] ] }
                }
            );
        }
        }
      } else {
        for ( let i = 0; i < filtered_array[ dbt ].length; i++ ) {
        $terms.push( {
                terms: { [ 'terms.' + dbt +  '.slug' ]: [ filtered_array[ dbt ][ i ] ] }
            }
        );
        }
      }
    }

    // add policy status
    if ( policy_status ) {
      $terms.push( {
           terms: { [ 'terms.dbt-policy-status.slug' ]: [ 'dbv-active' ] }
         }
      );
    }

    esquery.post_filter = {
      bool: {
          must: [
              {
                  bool: {
                      must: $terms
                  }
              },
              {
                  term: {
                      "post_type.raw": post_type
                  }
              },
              {
                  terms: {
                      post_status: [
                          "publish",
                          "acf-disabled"
                      ]
                  }
              }
          ]
      }
    };

    if ( agree_score ) {
      esquery.post_filter.bool.must.push( {
            bool: {
                must: [
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-scope-purpose.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-stakeholder-involvement.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-rigor.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-clarity-presentation.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-applicability.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    },
                    {
                        bool: {
                            must_not: [
                                {
                                    terms: {
                                        "meta.agree-editorial-independence.raw": [
                                            ""
                                        ]
                                    }
                                }
                            ]
                        }
                    }
                ]
            }
        }
      );
    }

    return esquery;
  } else {
    return;
  }
}

/**
 * Repository main landing page, set vale when user selects
 * a single filter option
 *
 */
var filter_links = document.querySelectorAll('.taxonomy-links');

for (var inc = 0; inc < filter_links.length; inc++) {
    filter_links[inc ].addEventListener('click', function (event) {
        event.preventDefault();

        // grab the hidden form items
        var dbt = document.querySelector('#dbt');
        var dbv = document.querySelector('#dbv');
        var map_selected = document.querySelector('#map_selected');

        // fill hidden fields with values
        dbt.value = this.getAttribute('data-type');
        dbv.value = this.getAttribute('data-value');
        map_selected.value = 'false';

        document.querySelector('#searchform').submit();
    } );
}


/*
 *----------------------------------------
 * News filtering/results
 *----------------------------------------
 */
var News = {
    news_offset: 6,
    news_total: 15,
    filter: 'All',
    number: 0,
    loading: false,
    items_to_load: 0,
    tag_id: 0,
    initNews: function () {
        News.initOffset();
    },
    initOffset: function () {
        var data = {
            'action': 'corporate_site_news_offset',
            'filter': News.filter,
            'tag_id': News.tag_id,
            'nonce': cpac.nonce
        };

        jQuery.ajax({
            type: "post",
            dataType: 'json',
            url: cpac.ajaxurl,
            data: data,
            success: function (data) {
                News.number = data;
            },
            error: function (xhr, errorType, exception) {
                console.log('There has been a ' + errorType + ', ' + exception + ' !');
            }
        });

        return false;
    },
    filterUpdate: function () {
        News.tag_id = jQuery('#news-tag-id').text();
        News.news_total = 15;
        News.filter = this.value;
        News.initOffset();
        News.loadFilteredItems();
    },
    loadFilteredItems: function () {
        var data = {
            'action': 'corporate_site_filtered_items',
            'filter': News.filter,
            'tag_id': News.tag_id,
            'nonce': cpac.nonce
        };

        jQuery('#news-year').remove();

        jQuery.ajax({
            type: "post",
            dataType: 'json',
            url: cpac.ajaxurl,
            data: data,
            beforeSend: function (xhr) {
                jQuery('#news > .container').css({ 'opacity': 0.2 });
                jQuery('#news').append('<div class="container spinner-overlay"><i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i><span class="sr-only">Loading News stories...</span></div>');
            },
            success: function (data) {
                jQuery('.spinner-overlay').remove();
                jQuery('#news > .container').css({ 'opacity': 1 });
                jQuery('#news > .container').empty();
                jQuery('#news > .container').append(data);
            },
            error: function (xhr, errorType, exception) {
                console.log('There has been a ' + errorType + ', ' + exception + ' !');
            }
        });

        return false;
    },
    loadMore: function () {
        News.tag_id = jQuery('#news-tag-id').text();
        var button = jQuery(this),
            current_year = jQuery('#news-year').text(),
            data = {
                'action': 'corporate_site_load_more_items',
                'offset': News.news_total,
                'filter': News.filter,
                'current_year': current_year,
                'tag_id': News.tag_id,
                'nonce': cpac.nonce
            };

        jQuery('#news-year').remove();

        jQuery.ajax({
            type: "post",
            dataType: 'json',
            url: cpac.ajaxurl,
            data: data,
            beforeSend: function (xhr) {
                button.html('<i class="fa fa-spinner fa-pulse fa-3x fa-fw"></i><span class="sr-only">Loading...</span>');
            },
            success: function (data) {
                if (data) {

                    // get the number of items left to load
                    News.items_to_load = News.number - News.news_total;

                    News.news_total += News.news_offset;
                    News.items_to_load -= News.news_offset;

                    button.text('Load more').before(data);

                    if (News.items_to_load < 0) {
                        button.remove();
                    }
                }
            },
            error: function (xhr, errorType, exception) {
                console.log('There has been a ' + errorType + ', ' + exception + ' !');
            }
        });
    }
};

if (document.querySelector('.page-template-news')) {
    News.initNews();
}