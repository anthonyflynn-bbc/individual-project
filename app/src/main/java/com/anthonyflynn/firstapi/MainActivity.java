package com.anthonyflynn.firstapi;

import android.os.AsyncTask;
import android.os.Bundle;
import android.support.design.widget.FloatingActionButton;
import android.support.design.widget.Snackbar;
import android.support.v7.app.AppCompatActivity;
import android.support.v7.widget.Toolbar;
import android.util.Log;
import android.view.View;
import android.view.Menu;
import android.view.MenuItem;
import android.widget.Button;
import android.widget.EditText;
import android.widget.ProgressBar;
import android.widget.TextView;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;
import org.json.JSONTokener;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class MainActivity extends AppCompatActivity {

    EditText busStop;
    TextView responseView;
    ProgressBar progressBar;
    Button queryButton;
    static final String API_URL = "https://api.tfl.gov.uk/Line/155/Arrivals?"; //add private here
    static final String API_ID = "";
    static final String API_KEY = "";
    private static final String TIME_TO_ARRIVAL = "timeToStation";
    private static final String DESTINATION = "destinationName";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        Toolbar toolbar = (Toolbar) findViewById(R.id.toolbar);
        setSupportActionBar(toolbar);

        busStop = (EditText) findViewById(R.id.busStop);
        responseView = (TextView) findViewById(R.id.responseView);
        progressBar = (ProgressBar) findViewById(R.id.progressBar);
        queryButton = (Button) findViewById(R.id.queryButton);

        FloatingActionButton fab = (FloatingActionButton) findViewById(R.id.fab);
        fab.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View view) {
                Snackbar.make(view, "Replace with your own action", Snackbar.LENGTH_LONG)
                        .setAction("Action", null).show();
            }
        }); // Snackbar - provides lightweight feedback about an operation (popup message)

        queryButton.setOnClickListener(new View.OnClickListener() {
            @Override
            public void onClick(View v) {
                String stop = busStop.getText().toString();
                new RetrieveFeedTask().execute(stop);
            }
        });

    }

    /*
    @Override
    public boolean onCreateOptionsMenu(Menu menu) {
        // Inflate the menu; this adds items to the action bar if it is present.
        getMenuInflater().inflate(R.menu.menu_main, menu);
        return true;
    }

    @Override
    public boolean onOptionsItemSelected(MenuItem item) {
        // Handle action bar item clicks here. The action bar will
        // automatically handle clicks on the Home/Up button, so long
        // as you specify a parent activity in AndroidManifest.xml.
        int id = item.getItemId();

        //noinspection SimplifiableIfStatement
        if (id == R.id.action_settings) {
            return true;
        }

        return super.onOptionsItemSelected(item);
    }
    */

    // Uses AsyncTask to create a task away from the main UI thread. This task uses
    // user input text to form a URL/create an HttpUrlConnection. Once the connection
    // has been established, the AsyncTask downloads the contents of the webpage as
    // an InputStream. Finally, the InputStream is converted into a string, which is
    // displayed in the UI by the AsyncTask's onPostExecute method.
    private class RetrieveFeedTask extends AsyncTask<String, Void, String> {

        private Exception exception;

        protected void onPreExecute() {
            progressBar.setVisibility(View.VISIBLE);
            responseView.setText("");

            /*
            //TESTING THAT USER HAS INTERNET CONNECTION:
            ConnectivityManager connMgr = (ConnectivityManager)
                getSystemService(Context.CONNECTIVITY_SERVICE);
            NetworkInfo networkInfo = connMgr.getActiveNetworkInfo();
            if (networkInfo != null && networkInfo.isConnected()) {
                // fetch data
            } else {
                responseView.setText("No network connection available.");
            }
             */

        }

        protected String doInBackground(String... stops) { //3 dots means any number of parameters (including none)
            String stop = stops[0];
            // Do some validation here
            try {
                //URL url = new URL(API_URL + "stopPointId=" + stop + "&app_id=" + API_ID + "&app_key=" + API_KEY);
                URL url = new URL("http://countdown.api.tfl.gov.uk/interfaces/ura/stream?Stopid=99&ReturnList=DestinationName,EstimatedTime");
                HttpURLConnection urlConnection = (HttpURLConnection) url.openConnection();
                try {
                    BufferedReader bufferedReader = new BufferedReader(new InputStreamReader(urlConnection.getInputStream()));
                    StringBuilder stringBuilder = new StringBuilder();
                    String line;
                    while ((line = bufferedReader.readLine()) != null) {
                        stringBuilder.append(line).append("\n");
                    }
                    bufferedReader.close();
                    return stringBuilder.toString();
                } finally {
                    urlConnection.disconnect();
                }
            } catch (Exception e) {
                Log.e("ERROR", e.getMessage(), e);
                return null;
            }
        }

        protected void onPostExecute(String response) {
            if (response == null) {
                response = "THERE WAS AN ERROR";
            }
            progressBar.setVisibility(View.GONE);
            Log.i("INFO", response); // API for sending log output

            String data = "";
            try {
                JSONArray returnedData = new JSONArray(response);
                for(int i = 0; i < returnedData.length(); i++) {
                    JSONObject next = returnedData.getJSONObject(i);
                    String dest = next.getString(DESTINATION);
                    int time = Integer.parseInt(next.getString(TIME_TO_ARRIVAL));
                    data += dest + "  " + time/60 + " mins\n";
                }
            } catch (JSONException e) {
                Log.e("ERROR", e.getMessage(), e);
            }

            responseView.setText(data);

        }
    }

}
